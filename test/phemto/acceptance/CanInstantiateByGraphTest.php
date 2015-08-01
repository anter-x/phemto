<?php

namespace phemto\acceptance;

use phemto\Phemto;
use phemto\lifecycle\Factory;
use phemto\lifecycle\Reused;
use phemto\lifecycle\Graph;


class CanInstantiateByGraphTest extends \PHPUnit_Framework_TestCase
{
    public function testInstantiateByGraphAndHint()
    {
        $injector = new Phemto();

        $injector->whenCreating(FirstClass::class, 'graph1')
            ->forVariable('property')
            ->useString('value for graph1');

        $injector->whenCreating(FirstClass::class, 'graph2')
            ->forVariable('property')
            ->useString('value for graph2');

        $injector->whenCreating(SecondClass::class, 'graph1')
            ->forVariable('property')
            ->useString('second value for graph1');

        $injector->whenCreating(SecondClass::class, 'graph2')
            ->forVariable('property')
            ->useString('second value for graph2');

        $object1 = $injector->createGraph(FirstClass::class, 'graph1');

        $this->assertEquals('value for graph1', $object1->property);
        $this->assertEquals('second value for graph1', $object1->dependency->property);

        $object2 = $injector->createGraph(FirstClass::class, 'graph2');

        $this->assertEquals('value for graph2', $object2->property);
        $this->assertEquals('second value for graph2', $object2->dependency->property);

        $this->assertNotSame($object2, $object1);
    }

    public function testInstantiateByGraphAndLifcycle()
    {
        $injector = new Phemto();

        $injector->whenCreating(ThirdClass::class, 'graph1')
            ->forVariable('dependency')
            ->willUse(new Factory(SecondClass::class));

        $injector->whenCreating(SecondClass::class, 'graph1')
            ->forVariable('property')
            ->useString('second value for graph1');

        $object1 = $injector->createGraph(ThirdClass::class, 'graph1');

        $this->assertEquals('second value for graph1', $object1->dependency->property);
    }

    public function testInstantiateByGraphAndVariable()
    {
        $injector = new Phemto();

        $injector->whenCreating(ThirdClass::class, 'graph1')
            ->forVariable('dependency')
            ->willUse(SecondClass::class);

        $injector->whenCreating(SecondClass::class, 'graph1')
            ->forVariable('property')
            ->useString('second value for graph1');

        $object1 = $injector->createGraph(ThirdClass::class, 'graph1');

        $this->assertEquals('second value for graph1', $object1->dependency->property);
    }

    public function testInstantiateByGraphAndDefaultDependency()
    {
        $injector = new Phemto();

        $injector->whenCreating(FirstClass::class, 'graph1')
            ->forVariable('property')
            ->useString('value for graph1');

        $injector->whenCreating(FirstClass::class, 'graph2')
            ->forVariable('property')
            ->useString('value for graph2');

        $injector->whenCreating(SecondClass::class)
            ->forVariable('property')
            ->useString('second value for default');

        $object1 = $injector->createGraph(FirstClass::class, 'graph1');

        $this->assertEquals('value for graph1', $object1->property);
        $this->assertEquals('second value for default', $object1->dependency->property);

        $object2 = $injector->createGraph(FirstClass::class, 'graph2');

        $this->assertEquals('value for graph2', $object2->property);
        $this->assertEquals('second value for default', $object2->dependency->property);
    }

    public function testInstantiateSingletonByGraph()
    {
        $injector = new Phemto();

        $injector->willUse(new Reused(FirstClass::class));

        $injector->whenCreating(FirstClass::class, 'graph1')
            ->forVariable('property')
            ->useString('value for graph1');

        $injector->whenCreating(FirstClass::class, 'graph2')
            ->forVariable('property')
            ->useString('value for graph2');

        $injector->whenCreating(SecondClass::class)
            ->forVariable('property')
            ->useString('second value for default');

        $object1 = $injector->createGraph(FirstClass::class, 'graph1');
        $this->assertEquals('value for graph1', $object1->property);

        $object2 = $injector->createGraph(FirstClass::class, 'graph2');
        $this->assertEquals('value for graph2', $object2->property);

        $this->assertNotSame($object2, $object1);
        $this->assertSame($object1, $injector->createGraph(FirstClass::class, 'graph1'));
    }

    public function testInstantiateSingletonByDefaultGraph()
    {
        $injector = new Phemto();

        $injector->willUse(new Reused(FirstClass::class));

        $injector->whenCreating(FirstClass::class)
            ->forVariable('property')
            ->useString('value for default');

        $injector->whenCreating(SecondClass::class)
            ->forVariable('property')
            ->useString('second value for default');

        $object1 = $injector->create(FirstClass::class);
        $this->assertEquals('value for default', $object1->property);
        $this->assertEquals('second value for default', $object1->dependency->property);

        $this->assertSame($object1, $injector->create(FirstClass::class));
    }

    public function testInstantiateByDifferentGraphsAndHint()
    {
        $injector = new Phemto();

        $injector->whenCreating(FirstClass::class, 'graph1')
            ->forVariable('property')
            ->useString('value for graph1');

        $injector->willUse(new Graph(SecondClass::class, 'graph2'));
        // if you use "Graph" factory, you will specify default context
        $injector->whenCreating(SecondClass::class)
            ->forVariable('property')
            ->useString('second value for graph2');

        $object1 = $injector->createGraph(FirstClass::class, 'graph1');

        $this->assertEquals('value for graph1', $object1->property);
        $this->assertEquals('second value for graph2', $object1->dependency->property);
    }

    public function testInstantiateByDifferentGraphsAndVariable()
    {
        $injector = new Phemto();

        $injector->whenCreating(ThirdClass::class, 'graph1')
            ->forVariable('dependency')
            ->willUse(new Graph(SecondClass::class, 'graph2'));

         // if you use "Graph" factory, you will specify default context
        $injector->whenCreating(SecondClass::class)
            ->forVariable('property')
            ->useString('second value for graph2');

        $object1 = $injector->createGraph(ThirdClass::class, 'graph1');

        $this->assertEquals('second value for graph2', $object1->dependency->property);
    }
}


class FirstClass
{
    /**
     * @var SecondClass
     */
    public $dependency;

    public $property;

    public function __construct(SecondClass $dependency, $property)
    {
        $this->dependency = $dependency;
        $this->property = $property;
    }
}

class SecondClass
{
    public $property;

    public function __construct($property)
    {
        $this->property = $property;
    }
}

class ThirdClass
{
    /**
     * @var SecondClass
     */
    public $dependency;

    public function __construct($dependency)
    {
        $this->dependency = $dependency;
    }

}