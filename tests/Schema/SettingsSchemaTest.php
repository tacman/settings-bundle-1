<?php

namespace Jbtronics\SettingsBundle\Tests\Schema;

use Jbtronics\SettingsBundle\ParameterTypes\BoolType;
use Jbtronics\SettingsBundle\ParameterTypes\IntType;
use Jbtronics\SettingsBundle\ParameterTypes\StringType;
use Jbtronics\SettingsBundle\Metadata\Settings;
use Jbtronics\SettingsBundle\Metadata\SettingsParameter;
use Jbtronics\SettingsBundle\Schema\ParameterSchema;
use Jbtronics\SettingsBundle\Schema\SettingsSchema;
use PhpParser\Node\Param;
use PHPUnit\Framework\TestCase;

class SettingsSchemaTest extends TestCase
{
    private SettingsSchema $configSchema;
    private Settings $configClass;
    private array $parameterSchema = [];

    public function setUp(): void
    {
        $this->parameterSchema = [
            new ParameterSchema(self::class, 'property1', IntType::class),
            new ParameterSchema(self::class, 'property2', StringType::class, 'name2'),
            new ParameterSchema(self::class, 'property3', BoolType::class, 'name3', 'label3', 'description3'),
        ];

        $this->configSchema = new SettingsSchema(
            self::class,
            $this->parameterSchema
        );
    }

    public function testGetClassName(): void
    {
        $this->assertEquals(self::class, $this->configSchema->getClassName());
    }

    public function testGetParameters(): void
    {
        $this->assertEquals([
            'property1' => $this->parameterSchema[0],
            'name2' => $this->parameterSchema[1],
            'name3' => $this->parameterSchema[2],
        ], $this->configSchema->getParameters());
    }

    public function testHasParameter(): void
    {
        $this->assertTrue($this->configSchema->hasParameter('property1'));
        $this->assertTrue($this->configSchema->hasParameter('name2'));
        $this->assertTrue($this->configSchema->hasParameter('name3'));
        $this->assertFalse($this->configSchema->hasParameter('property4'));
    }

    public function testGetParameter(): void
    {
        $this->assertEquals($this->parameterSchema[0], $this->configSchema->getParameter('property1'));
        $this->assertEquals($this->parameterSchema[1], $this->configSchema->getParameter('name2'));
        $this->assertEquals($this->parameterSchema[2], $this->configSchema->getParameter('name3'));
    }

    public function testGetParameterInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->configSchema->getParameter('property4');
    }

    public function testGetParameterByPropertyName(): void
    {
        $this->assertEquals($this->parameterSchema[0], $this->configSchema->getParameterByPropertyName('property1'));
        $this->assertEquals($this->parameterSchema[1], $this->configSchema->getParameterByPropertyName('property2'));
        $this->assertEquals($this->parameterSchema[2], $this->configSchema->getParameterByPropertyName('property3'));
    }

    public function testGetParameterByPropertyNameInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->configSchema->getParameterByPropertyName('property4');
    }

    public function testHasParameterWithPropertyName(): void
    {
        $this->assertTrue($this->configSchema->hasParameterWithPropertyName('property1'));
        $this->assertTrue($this->configSchema->hasParameterWithPropertyName('property2'));
        $this->assertTrue($this->configSchema->hasParameterWithPropertyName('property3'));
        $this->assertFalse($this->configSchema->hasParameterWithPropertyName('property4'));
    }

    public function testGetPropertyNames(): void
    {
        $this->assertEquals([
            'property1',
            'property2',
            'property3',
        ], $this->configSchema->getPropertyNames());
    }
}