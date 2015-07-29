<?php

namespace phemto\acceptance;

use phemto\Phemto;
use phemto\lifecycle\Reused;


class DetermineImplementationFromTopContextTest extends \PHPUnit_Framework_TestCase
{
    public function testDetermineImplementationFromTopContext()
    {
        $injector = new Phemto();
        $injector->willUse(new Reused(Preference::class));

        $injector->whenCreating(HasDependency::class)
            ->forVariable('dependency')
                ->willUse(Preference::class);

        $object = $injector->create(HasDependency::class);

        $this->assertSame(
            spl_object_hash($object->dependency),
            spl_object_hash($injector->create(Preference::class))
        );
    }
}


class Preference {}

class HasDependency
{
    public $dependency;

    public function __construct($dependency)
    {
        $this->dependency = $dependency;
    }
}