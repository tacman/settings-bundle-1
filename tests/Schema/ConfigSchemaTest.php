<?php

namespace Jbtronics\UserConfigBundle\Tests\Schema;

use Jbtronics\UserConfigBundle\ConfigEntryTypes\BoolType;
use Jbtronics\UserConfigBundle\ConfigEntryTypes\IntType;
use Jbtronics\UserConfigBundle\ConfigEntryTypes\StringType;
use Jbtronics\UserConfigBundle\Metadata\ConfigClass;
use Jbtronics\UserConfigBundle\Metadata\ConfigEntry;
use Jbtronics\UserConfigBundle\Schema\ConfigSchema;
use PHPUnit\Framework\TestCase;

class ConfigSchemaTest extends TestCase
{
    private ConfigSchema $configSchema;
    private ConfigClass $configClass;
    private array $propertyAttributes = [];

    public function setUp(): void
    {
        $this->configClass = new ConfigClass();
        $this->propertyAttributes = [
            'property1' => new ConfigEntry(IntType::class),
            'property2' => new ConfigEntry(StringType::class),
            'property3' => new ConfigEntry(BoolType::class),
        ];

        $this->configSchema = new ConfigSchema(
            'myClassName',
            $this->configClass,
            $this->propertyAttributes
        );
    }

    public function testGetClassName(): void
    {
        $this->assertEquals('myClassName', $this->configSchema->getClassName());
    }

    public function testGetConfigClassAttribute(): void
    {
        $this->assertEquals($this->configClass, $this->configSchema->getConfigClassAttribute());
    }

    public function testGetConfigEntryPropertyNames(): void
    {
        $this->assertEquals(['property1', 'property2', 'property3'], $this->configSchema->getConfigEntryPropertyNames());
    }

    public function testGetConfigEntryAttributes(): void
    {
        $this->assertEquals($this->propertyAttributes, $this->configSchema->getConfigEntryAttributes());
    }

    public function testGetConfigEntryAttribute()
    {
        $this->assertEquals($this->propertyAttributes['property1'], $this->configSchema->getConfigEntryAttribute('property1'));
    }
}