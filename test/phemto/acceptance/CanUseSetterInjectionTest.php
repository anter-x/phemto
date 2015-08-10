<?php

namespace phemto\acceptance;

use phemto\Phemto;
use phemto\lifecycle\Reused;


class NotWithoutMe
{
}

class NeedsInitToCompleteConstruction
{
	function init(NotWithoutMe $me)
	{
		@$this->me = $me;
	}
}

class SomeRegularClass
{
    /**
     * @var DependencyWithSetter
     */
    public $dependency;

    public function __construct($dependency)
    {
        $this->dependency = $dependency;
    }
}

class DependencyWithSetter
{
    /**
     * @var NotWithoutMe
     */
    public $property;

    public function setProperty(NotWithoutMe $property)
    {
        $this->property = $property;
    }
}

class CanUseSetterInjectionTest extends \PHPUnit_Framework_TestCase
{
	function testCanCallSettersToCompleteInitialisation()
	{
		$injector = new Phemto();
		$injector->forType('phemto\acceptance\NeedsInitToCompleteConstruction')->call('init');
		$expected = new NeedsInitToCompleteConstruction();
		$expected->init(new NotWithoutMe());
		$this->assertEquals(
			$injector->create('phemto\acceptance\NeedsInitToCompleteConstruction'),
			$expected
		);
	}

    public function testCanCallSettersForDependecy()
    {
        $injector = new Phemto();

        $injector->whenCreating(SomeRegularClass::class)
            ->forVariable('dependency')
            ->willUse(new Reused(DependencyWithSetter::class));

        $injector->forType(DependencyWithSetter::class)
            ->call('setProperty');

        $object = $injector->create(SomeRegularClass::class);

        $this->assertInstanceOf(NotWithoutMe::class, $object->dependency->property);
    }
}