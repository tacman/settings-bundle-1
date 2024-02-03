<?php


/*
 * Copyright (c) 2024 Jan Böhmer
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Jbtronics\SettingsBundle\Metadata;

use Jbtronics\SettingsBundle\Helper\PropertyAccessHelper;
use Jbtronics\SettingsBundle\Helper\ProxyClassNameHelper;
use Jbtronics\SettingsBundle\Manager\SettingsRegistry;
use Jbtronics\SettingsBundle\Manager\SettingsRegistryInterface;
use Jbtronics\SettingsBundle\ParameterTypes\ParameterTypeInterface;
use Jbtronics\SettingsBundle\ParameterTypes\ParameterTypeRegistryInterface;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Storage\InMemoryStorageAdapter;
use Symfony\Contracts\Cache\CacheInterface;

final class MetadataManager implements MetadataManagerInterface
{
    private array $metadata_cache = [];

    private const CACHE_KEY_PREFIX = 'jbtronics_settings.metadata.';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly bool $debug_mode,
        private readonly SettingsRegistryInterface $settingsRegistry,
        private readonly ParameterTypeGuesserInterface $parameterTypeGuesser,
        private readonly string $defaultStorageAdapter = InMemoryStorageAdapter::class,
    ) {
    }

    public function isSettingsClass(string|object $className): bool
    {
        //If the given name is not a class name, try to resolve the name via SettingsRegistry
        if (!class_exists($className)) {
            $className = $this->settingsRegistry->getSettingsClassByName($className);
        }

        //Check if the given class contains a #[ConfigClass] attribute.
        //If yes, return true, otherwise return false.

        $reflClass = new \ReflectionClass($className);
        $attributes = $reflClass->getAttributes(Settings::class);

        return count($attributes) > 0;
    }

    public function getSettingsMetadata(string|object $className): SettingsMetadata
    {
        if (is_object($className)) {
            $className = ProxyClassNameHelper::resolveEffectiveClass($className);
        } elseif (!class_exists($className)) { //If the given name is not a class name, try to resolve the name via SettingsRegistry
            $className = $this->settingsRegistry->getSettingsClassByName($className);
        }

        //Check if the metadata for the given class is already cached.
        if (isset($this->metadata_cache[$className])) {
            return $this->metadata_cache[$className];
        }

        if ($this->debug_mode) {
            $metadata = $this->getMetadataUncached($className);
        } else {
            $metadata = $this->cache->get(self::CACHE_KEY_PREFIX.md5($className), function () use ($className) {
                return $this->getMetadataUncached($className);
            });
        }

        $this->metadata_cache[$className] = $metadata;
        return $metadata;
    }

    private function getMetadataUncached(string $className): SettingsMetadata
    {
        //If not, create it and cache it.

        //Retrieve the #[ConfigClass] attribute from the given class.
        $reflClass = new \ReflectionClass($className);
        $attributes = $reflClass->getAttributes(Settings::class);

        if (count($attributes) < 1) {
            throw new \LogicException(sprintf('The class "%s" is not a config class. Add the #[ConfigClass] attribute to the class.',
                $className));
        }
        if (count($attributes) > 1) {
            throw new \LogicException(sprintf('The class "%s" has more than one ConfigClass atrributes! Only one is allowed',
                $className));
        }

        /** @var Settings $classAttribute */
        $classAttribute = $attributes[0]->newInstance();
        $parameters = [];

        //Retrieve all ConfigEntry attributes on the properties of the given class
        $reflProperties = PropertyAccessHelper::getProperties($className);
        foreach ($reflProperties as $reflProperty) {
            $attributes = $reflProperty->getAttributes(SettingsParameter::class);
            //Skip properties without a ConfigEntry attribute
            if (count($attributes) < 1) {
                continue;
            }
            if (count($attributes) > 1) {
                throw new \LogicException(sprintf('The property "%s" of the class "%s" has more than one ConfigEntry atrributes! Only one is allowed',
                    $reflProperty->getName(), $className));
            }

            //Add it to our list
            /** @var SettingsParameter $attribute */
            $attribute = $attributes[0]->newInstance();

            //Try to guess type
            $type = $attribute->type ?? $this->parameterTypeGuesser->guessParameterType($reflProperty);
            if ($type === null) {
                throw new \LogicException(sprintf('The property "%s" of the class "%s" has no type set and the type could not be guessed. Please set the type explicitly!',
                    $reflProperty->getName(), $className));
            }

            //Try to guess extra options
            $options = array_merge($this->parameterTypeGuesser->guessOptions($reflProperty) ?? [], $attribute->options);

            //Try to determine whether the property is nullable
            $nullable = $attribute->nullable;
            if ($nullable === null) {
                //Check if the type is nullable
                $nullable = $reflProperty->getType()?->allowsNull();
                //If the type is not declared then nullable is still null. Throw an exception then
                if ($nullable === null) {
                    throw new \LogicException(sprintf('The property "%s" of the class "%s" has no type declaration and the nullable flag could not be guessed. Please set the nullable flag explicitly!',
                        $reflProperty->getName(), $className));
                }
            }

            $parameters[] = new ParameterMetadata(
                className: $className,
                propertyName: $reflProperty->getName(),
                type: $attribute->type ?? $type,
                nullable: $nullable,
                name: $attribute->name,
                label: $attribute->label,
                description: $attribute->description,
                options: $options,
                formType: $attribute->formType,
                formOptions: $attribute->formOptions,
                groups: $attribute->groups ?? $classAttribute->groups ?? [],
            );
        }

        //Ensure that the settings version is greather than 0
        if ($classAttribute->version !== null && $classAttribute->version <= 0) {
            throw new \LogicException(sprintf("The version of the settings class %s must be greater than zero! %d given", $className, $classAttribute->version));
        }

        //Now we have all infos required to build our settings metadata
        return new SettingsMetadata(
            className: $className,
            parameterMetadata: $parameters,
            storageAdapter: $classAttribute->storageAdapter ?? $this->defaultStorageAdapter,
            name: $classAttribute->name ?? SettingsRegistry::generateDefaultNameFromClassName($className),
            defaultGroups: $classAttribute->groups,
            version: $classAttribute->version,
            migrationService: $classAttribute->migrationService,
            storageAdapterOptions: $classAttribute->storageAdapterOptions,
        );
    }
}