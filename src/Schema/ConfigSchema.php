<?php

namespace Jbtronics\UserConfigBundle\Schema;

use Jbtronics\UserConfigBundle\Metadata\ConfigClass;
use Jbtronics\UserConfigBundle\Metadata\ConfigEntry;

class ConfigSchema
{
    public function __construct(
        private readonly string $className,
        private readonly ConfigClass $classAttribute,
        private readonly array $configEntryProperties,
    )
    {

    }

    /**
     * Returns the class name of the configuration class, which is managed by this schema.
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * Returns the config class attribute associated with this schema.
     * @return ConfigClass
     */
    public function getConfigClassAttribute(): ConfigClass
    {
        return $this->classAttribute;
    }

    /**
     * Returns an array of all config entry attributes of the configuration class, which is managed by this schema.
     * The array is indexed by the property names.
     * @return array
     */
    public function getConfigEntryAttributes(): array
    {
        return $this->configEntryProperties;
    }

    /**
     * Return a list of the property names of the configuration class, which is managed by this schema.
     * @return array
     */
    public function getConfigEntryPropertyNames(): array
    {
        return array_keys($this->configEntryProperties);
    }

    /**
     * Returns the config entry attribute of the given property name.
     * @param  string  $propertyName
     * @return ConfigEntry
     */
    public function getConfigEntryAttribute(string $propertyName): ConfigEntry
    {
        if (!isset($this->configEntryProperties[$propertyName])) {
            throw new \InvalidArgumentException(sprintf('The property "%s" is not a config entry of the class "%s"', $propertyName, $this->className));
        }

        return $this->configEntryProperties[$propertyName];
    }
}