<?php

namespace phemto\acceptance;

use phemto\Phemto;
use phemto\lifecycle\ListOf;
use phemto\lifecycle\Graph;


class CanInstantiateListOfDependenciesTest extends \PHPUnit_Framework_TestCase
{
    public function testInstantiateListOfDependenciesDeclareInSubcontext()
    {
        $injector = new Phemto();

        $injector->whenCreating(RequiresDependencies::class)
            ->forVariable('dependencies')
            ->willUse(new ListOf(
                new Graph(InstantiatedByGrathDependency::class, 'graph1'),
                AutomaticallyInstantiatedDependency::class,
                new DirectlyInstantiatedDependency()
            ));

        $injector->whenCreating(InstantiatedByGrathDependency::class)
            ->forVariable('property')
            ->useString('property for graph1');

        $object = $injector->create(RequiresDependencies::class);

        $this->assertCount(3, $object->dependencies);
        $this->assertInstanceOf(InstantiatedByGrathDependency::class, $object->dependencies[0]);
        $this->assertInstanceOf(AutomaticallyInstantiatedDependency::class, $object->dependencies[1]);
        $this->assertInstanceOf(DirectlyInstantiatedDependency::class, $object->dependencies[2]);

        $this->assertEquals('property for graph1', $object->dependencies[0]->property);
    }

    public function testInstantiateListOfDependenciesDeclareInTopContext()
    {
        $injector = new Phemto();

        $injector->forVariable('dependencies')
            ->willUse(new ListOf(
                AutomaticallyInstantiatedDependency::class
            ));

        $object = $injector->create(RequiresDependencies::class);

        $this->assertInstanceOf(AutomaticallyInstantiatedDependency::class, $object->dependencies[0]);
    }
}



class RequiresDependencies
{
    public $dependencies = [];

    public function __construct($dependencies)
    {
        $this->dependencies = $dependencies;
    }
}

class InstantiatedByGrathDependency
{
    public $property;

    public function __construct($property)
    {
        $this->property = $property;
    }
}

class AutomaticallyInstantiatedDependency {}

class DirectlyInstantiatedDependency {}