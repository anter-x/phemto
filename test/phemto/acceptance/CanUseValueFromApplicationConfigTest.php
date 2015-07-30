<?php

namespace phemto\acceptance;

use phemto\Phemto;
use phemto\lifecycle\ConfigValue;


class CanUseValueFromApplicationConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testUsingApplicationConfigValue()
    {
        $injector = new Phemto();
        $injector->forVariable(ConfigValue::CONFIG_NAME)
            ->willUse(SomeApplicationConfig::class);

        $injector->forVariable('property')
            ->useConfig('config_param1');

        $object = $injector->create(SomeClassNeedConfigValue::class);

        $this->assertEquals(
            (new SomeApplicationConfig())->data['config_param1'],
            $object->property
        );
    }
}


class SomeApplicationConfig
{
    public $data = [
        'config_param1' => 'config value 1',
        'config_param2' => 'config value 2',
    ];

    public function get($param)
    {
        return isset($this->data[$param]) ? $this->data[$param] : null;
    }
}


class SomeClassNeedConfigValue
{
    public $property;

    public function __construct($property)
    {
        $this->property = $property;
    }
}