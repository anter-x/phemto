<?php

namespace phemto\acceptance;

use phemto\Phemto;
use phemto\lifecycle\ReusedByParam;


class CanInstantiateObjectsAsSingletonsByParamTest extends \PHPUnit_Framework_TestCase
{
    public function testSameInstanceCanBeReusedWithinFactoryDependingOnStringParam()
    {
        $injector = new Phemto();

        $injector->willUse(new ReusedByParam(CreateMeOnceByStringParam::class, 'property'));

        $object1 = $injector->create(CreateMeOnceByStringParam::class, 'value1');
        $object2 = $injector->create(CreateMeOnceByStringParam::class, 'value1');
        $object3 = $injector->create(CreateMeOnceByStringParam::class, 'value2');

        $this->assertSame($object1, $object2);
        $this->assertNotSame($object1, $object3);
    }

    public function testSameInstanceCanBeReusedWithinFactoryDependingOnObjectParam()
    {
        $injector = new Phemto();

        $injector->willUse(new ReusedByParam(CreateMeOnceByObjectParam::class, 'dependency'));

        $dependency = new SomeDependency();

        $object1 = $injector->create(CreateMeOnceByObjectParam::class, $dependency);
        $object2 = $injector->create(CreateMeOnceByObjectParam::class, $dependency);
        $object3 = $injector->create(CreateMeOnceByObjectParam::class, new SomeDependency());

        $this->assertSame($object1, $object2);
        $this->assertNotSame($object1, $object3);
    }
}


class CreateMeOnceByStringParam
{
    public $property;

    public function __construct($property)
    {
        $this->property = $property;
    }
}

class CreateMeOnceByObjectParam
{
    public $dependency;

    public function __construct($dependency)
    {
        $this->dependency = $dependency;
    }
}


class SomeDependency {}