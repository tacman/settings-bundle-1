<?php

namespace Jbtronics\SettingsBundle\Tests\TestApplication\Manager;

use Jbtronics\SettingsBundle\Manager\SettingsResetter;
use Jbtronics\SettingsBundle\Manager\SettingsResetterInterface;
use Jbtronics\SettingsBundle\ParameterTypes\BoolType;
use Jbtronics\SettingsBundle\ParameterTypes\IntType;
use Jbtronics\SettingsBundle\ParameterTypes\StringType;
use Jbtronics\SettingsBundle\Schema\ParameterSchema;
use Jbtronics\SettingsBundle\Schema\SettingsSchema;
use Jbtronics\SettingsBundle\Settings\ResettableSettingsInterface;
use Jbtronics\SettingsBundle\Storage\InMemoryStorageAdapter;
use Jbtronics\SettingsBundle\Tests\TestApplication\Settings\SimpleSettings;
use PHPUnit\Framework\TestCase;

class SettingsResetterTest extends TestCase
{

    private SettingsResetterInterface $service;

    public function setUp(): void
    {
        $this->service = new SettingsResetter();
    }

    public function testResetSettingsOnlyProperties(): void
    {
        $this->service = new SettingsResetter();

        $settings = new SimpleSettings();
        $settings->setValue1('changed');
        $settings->setValue2(42);
        $settings->setValue3(true);

        //Create a schema with all properties
        $schema = new SettingsSchema(
            className: get_class($settings),
            parameterSchemas: [
                new ParameterSchema(className: get_class($settings), propertyName: 'value1', type: StringType::class),
                new ParameterSchema(className: get_class($settings), propertyName: 'value2', type: IntType::class),
                //This property is deliberately left out, and it should not be resetted
                //new ParameterSchema(className: get_class($settings), propertyName: 'value3', type: BoolType::class),
            ],
            storageAdapter: InMemoryStorageAdapter::class,
            name: 'test'
        );

        $this->service->resetSettings($settings, $schema);

        $this->assertEquals('default', $settings->getValue1());
        $this->assertNull($settings->getValue2());
        //This property must not been resetted
        $this->assertTrue($settings->getValue3());
    }

    public function testResetSettingsResettableInterface(): void
    {
        $this->service = new SettingsResetter();

        $settings = new class implements ResettableSettingsInterface {

            public string $value1 = 'default';
            public int $value2;
            public ?bool $value3;

            public bool $wasResetted = false;

            public function resetToDefaultValues(): void
            {
                //Set the default value of value 2
                $this->value2 = 42;

                //Mark that this class was resetted
                $this->wasResetted = true;
            }
        };


        $settings->value1 = 'changed';
        $settings->value2 = 42;
        $settings->value3 = true;

        //Create a schema with all properties
        $schema = new SettingsSchema(
            className: get_class($settings),
            parameterSchemas: [
                new ParameterSchema(className: get_class($settings), propertyName: 'value1', type: StringType::class),
                new ParameterSchema(className: get_class($settings), propertyName: 'value2', type: IntType::class),
                new ParameterSchema(className: get_class($settings), propertyName: 'value3', type: BoolType::class),
            ],
            storageAdapter: InMemoryStorageAdapter::class,
            name: 'test'
        );

        $this->service->resetSettings($settings, $schema);

        //The reset method should have been called
        $this->assertTrue($settings->wasResetted);

        //And the properties should have been resetted
        $this->assertEquals('default', $settings->value1);
        $this->assertSame(42, $settings->value2);
        //This property must be null now, as the not explicitly set value of a nullable property is null
        $this->assertNull($settings->value3);
    }

    public function testResetSettingsNotResetableProperty(): void
    {
        //If we encounter a property without a default value and which is not nullable, we throw an exception
        $this->expectException(\LogicException::class);

        $settings = new class {
            public string $value1;

            public function resetToDefaultValues(): void
            {
                //Set the default value of value 2
                $this->value2 = 42;

                //Mark that this class was resetted
                $this->wasResetted = true;
            }
        };

        $schema = new SettingsSchema(
            className: get_class($settings),
            parameterSchemas: [
                new ParameterSchema(className: get_class($settings), propertyName: 'value1', type: StringType::class),
            ],
            storageAdapter: InMemoryStorageAdapter::class,
            name: 'test'
        );

        $this->service->resetSettings($settings, $schema);
    }
}
