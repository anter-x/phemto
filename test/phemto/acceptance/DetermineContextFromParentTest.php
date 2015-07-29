<?php

namespace phemto\acceptance;

use phemto\Phemto;


class DetermineContextFromParentTest extends \PHPUnit_Framework_TestCase
{
    public function testDetermineContextFromParent()
    {
        $injector = new Phemto();

        $injector->whenCreating(FromContextTwo::class)
            ->forVariable('property')
            ->useString('property_value');

        $injector->whenCreating(FromContextOne::class)
            ->forVariable('param')
            ->useString('param_value');

        $object = $injector->create(FromContextOne::class);

        $this->assertEquals('property_value', $object->dependency->property);
    }
}


class FromContextOne
{
    public $dependency;

    public function __construct(FromContextTwo $dependency, $param)
    {
        $this->dependency = $dependency;
    }
}


class FromContextTwo
{
    public $property;

    public function __construct($property)
    {
        $this->property = $property;
    }
}