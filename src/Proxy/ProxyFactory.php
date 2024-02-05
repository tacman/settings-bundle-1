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


namespace Jbtronics\SettingsBundle\Proxy;

use Jbtronics\SettingsBundle\Metadata\SettingsMetadata;
use Symfony\Component\VarExporter\ProxyHelper;

/**
 * This class manages the generation of proxy classes for lazy loading of settings.
 * This class is inspired by the class with the same name in the Doctrine ORM:
 * https://github.com/doctrine/orm/blob/3.0.x/src/Proxy/ProxyFactory.php#L430
 */
class ProxyFactory implements ProxyFactoryInterface
{

    private const PROXY_TEMPLATE = <<<'PHP'
<?php

namespace <namespace>;

/**
 * DO NOT EDIT THIS FILE - IT WAS CREATED BY JBTRONICS/SETTINGS-BUNDLE
 */
class <proxyShortClassName>
PHP;


    public function __construct(
        private readonly string $proxyDir,
        private readonly string $proxyNamespace,
    ) {

    }

    /**
     * Returns the directory, where the proxy classes are stored.
     */
    public function getProxyCacheDir(): string
    {
        return $this->proxyDir;
    }

    /**
     * Generates the proxy classes for the given metadata.
     * @param  string[]  $classes
     * @return void
     * @throws \ReflectionException
     */
    public function generateProxyClassFiles(array $classes): void
    {
        foreach ($classes as $class) {
            $this->generateProxyClassFile($class);
        }
    }

    /**
     * Creates a new proxy instance for the given settings class and initializer.
     * @param  string $class
     * @param  \Closure  $initializer
     * @return SettingsProxyInterface
     * @throws \ReflectionException
     */
    public function createProxy(string $class, \Closure $initializer): SettingsProxyInterface
    {
        $proxyClassName = $this->getProxyClassName($class);
        if (!class_exists($proxyClassName, false)) {
            // Load the proxy class file
            $proxyFileName = $this->getProxyFilename($class);
            //If the file not exists yet, generate it
            if (!file_exists($proxyFileName)) {
                $this->generateProxyClassFile($class);
            }

            //Load the proxy class
            require $proxyFileName;
        }

        return $proxyClassName::createLazyProxy(initializer: $initializer);
    }

    /**
     * Generate the proxy class file for the given class.
     * @param  string $className
     * @phpstan-param class-string $className
     * @return void
     * @throws \ReflectionException
     */
    private function generateProxyClassFile(string $className): void
    {
        $reflClass = new \ReflectionClass($className);
        $reflInterface = new \ReflectionClass(SettingsProxyInterface::class);
        $proxyNS = rtrim($this->proxyNamespace, '\\') . '\\' . SettingsProxyInterface::MARKER . '\\' . $reflClass->getNamespaceName();
        $proxyFileName = $this->getProxyFilename($className);
        $proxyShortName = $reflClass->getShortName();

        $proxyPrefix = strtr(self::PROXY_TEMPLATE, [
            '<namespace>' => $proxyNS,
            '<proxyShortClassName>' => $proxyShortName,
        ]);

        $proxyCode = $proxyPrefix . ProxyHelper::generateLazyProxy($reflClass, [$reflInterface]);

        $parentDirectory = dirname($proxyFileName);

        if (!is_dir($parentDirectory) && !mkdir($parentDirectory, 0775, true) && !is_dir($parentDirectory)) {
            throw new \RuntimeException(sprintf("Could not create directory '%s' to store settings proxies!", $parentDirectory));
        }

        if (! is_writable($parentDirectory)) {
            throw new \RuntimeException(sprintf("The directory '%s' is not writable!", $parentDirectory));
        }

        $tmpFileName = $proxyFileName . '.' . bin2hex(random_bytes(12));

        file_put_contents($tmpFileName, $proxyCode);
        @chmod($tmpFileName, 0664);
        rename($tmpFileName, $proxyFileName);
    }

    /**
     * Generates the filename for the proxy of the given class.
     * @param  string  $class
     * @return string
     */
    public function getProxyFilename(string $class): string
    {
        return rtrim($this->proxyDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . SettingsProxyInterface::MARKER
            . str_replace('\\', '', $class) . '.php';
    }

    /**
     * Generates the proxy class name for the given class (without namespace).
     * @param  string  $class
     * @return string
     */
    public function getProxyClassName(string $class): string
    {
        return rtrim($this->proxyNamespace, '\\') . '\\' . SettingsProxyInterface::MARKER . '\\' . ltrim($class, '\\');
    }
}