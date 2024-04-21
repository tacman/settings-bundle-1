<?php
/*
 * This file is part of jbtronics/settings-bundle (https://github.com/jbtronics/settings-bundle).
 *
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

declare(strict_types=1);


namespace Jbtronics\SettingsBundle\Manager;

use Jbtronics\SettingsBundle\Helper\PropertyAccessHelper;
use Jbtronics\SettingsBundle\Metadata\MetadataManager;
use Jbtronics\SettingsBundle\Proxy\ProxyFactoryInterface;
use Jbtronics\SettingsBundle\Proxy\SettingsProxyInterface;
use Jbtronics\SettingsBundle\Settings\CloneAndMergeAwareSettingsInterface;
use Symfony\Component\VarExporter\LazyObjectInterface;

class SettingsCloner implements SettingsClonerInterface
{
    public function __construct(
        private readonly MetadataManager $metadataManager,
        private readonly ProxyFactoryInterface $proxyFactory,
    )
    {
    }

    public function createClone(object $settings): object
    {
        $embedded_clones = [];
        return $this->createCloneInternal($settings, $embedded_clones);
    }

    private function createCloneInternal(object $settings, array &$embeddedClones): object
    {
        $metadata = $this->metadataManager->getSettingsMetadata($settings);

        //Use reflection to create a new instance of the settings class
        $clone = new \ReflectionClass($metadata->getClassName());

        //Iterate over all properties and copy them to the new instance
        foreach ($metadata->getParameters() as $parameter) {
            $oldVar = PropertyAccessHelper::getProperty($settings, $parameter->getPropertyName());

            //If the property is an object, we need to clone it, to get a new instance
            if (is_object($oldVar)) {
                $newVar = clone $oldVar;
            } else {
                $newVar = $oldVar;
            }

            //Set the property on the new instance
            PropertyAccessHelper::setProperty($clone, $parameter->getPropertyName(), $newVar);
        }

        //Iterate over all embedded settings
        foreach ($metadata->getEmbeddedSettings() as $embeddedSetting) {
            //If the embedded setting was already cloned, we can reuse it
            if (isset($embeddedClones[$embeddedSetting->getClassName()])) {
                $embeddedClone = $embeddedClones[$embeddedSetting->getClassName()];
            } else {
                //Otherwise, we need to create a new clone, which we lazy load, via our proxy system
                $embeddedClone = $this->proxyFactory->createProxy($embeddedSetting->getClassName(), function () use ($embeddedSetting, $settings, $embeddedClones) {
                    return $this->createCloneInternal(PropertyAccessHelper::getProperty($settings, $embeddedSetting->getPropertyName()), $embeddedClones);
                });
            }

            //Set the embedded clone on the new instance
            PropertyAccessHelper::setProperty($clone, $embeddedSetting->getPropertyName(), $embeddedClone);
        }

        //If the settings class implements the CloneAndMergeAwareSettingsInterface, call the afterClone method
        if ($clone instanceof CloneAndMergeAwareSettingsInterface) {
            $clone->afterSettingsClone($settings);
        }

        //Add the clone to the list of embedded clones, so that we can access it in other iterations of this method
        $embeddedClones[$metadata->getClassName()] = $clone;

        return $clone;
    }

    public function mergeCopyInternal(object $copy, object $into, bool $recursive, array &$mergedClasses): object
    {
        $metadata = $this->metadataManager->getSettingsMetadata($copy);

        //Iterate over all properties and copy them to the new instance
        foreach ($metadata->getParameters() as $parameter) {
            $oldVar = PropertyAccessHelper::getProperty($copy, $parameter->getPropertyName());

            //If the property is an object, we need to clone it, to get a new instance
            if (is_object($oldVar) ) {
                $newVar = clone $oldVar;
            } else {
                $newVar = $oldVar;
            }

            //Set the property on the new instance
            PropertyAccessHelper::setProperty($into, $parameter->getPropertyName(), $newVar);
        }

        //If recursive mode is active, also merge the embedded settings
        if ($recursive) {
            foreach ($metadata->getEmbeddedSettings() as $embeddedSetting) {
                //Skip if the class was already merged
                if (isset($mergedClasses[$embeddedSetting->getClassName()])) {
                    continue;
                }

                $copyEmbedded = PropertyAccessHelper::getProperty($copy, $embeddedSetting->getPropertyName());

                //If the embedded setting is a lazy proxy and it was not yet initialized, we can skip it as the data was not modified
                if ($copyEmbedded instanceof SettingsProxyInterface && $copyEmbedded instanceof LazyObjectInterface && !$copyEmbedded->isLazyObjectInitialized()) {
                    continue;
                }

                $intoEmbedded = PropertyAccessHelper::getProperty($into, $embeddedSetting->getPropertyName());

                //Recursively merge the embedded setting
                $this->mergeCopyInternal($copyEmbedded, $intoEmbedded, $recursive, $mergedClasses);
            }
        }

        $mergedClasses[$metadata->getClassName()] = $into;

        //If the settings class implements the CloneAndMergeAwareSettingsInterface, call the afterMerge method
        if ($into instanceof CloneAndMergeAwareSettingsInterface) {
            $into->afterSettingsMerge($copy);
        }

        return $into;
    }

    public function mergeCopy(object $copy, object $into, bool $recursive = true): object
    {
        $mergedClasses = [];
        return $this->mergeCopyInternal($copy, $into, $recursive, $mergedClasses);
    }
}