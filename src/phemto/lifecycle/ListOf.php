<?php

namespace phemto\lifecycle;

use phemto\Context;


class ListOf extends Lifecycle
{
    protected $preferences = [];

    public function __construct()
    {
        $this->preferences = func_get_args();
    }

    public function instantiate(Context $context, $nesting, $graph = null)
    {
        $instances = [];

        foreach ($this->preferences as $preference) {
            if ($preference instanceof Lifecycle) {
                $preferContext = (empty($preference->class)) ? $context : $context->determineContext($preference->class, $graph);
                $instance = $preference->instantiate($preferContext, $nesting, $graph);
                if ($preference instanceof Factory) {
                    $preferContext->invokeSetters($preferContext, $nesting, $preference->class, $instance, $graph);
                }
                $instances[] = $instance;
            } elseif (!is_string($preference)) {
                $instances[] = $preference;
            } else {
                $instances[] = $context->create($preference, $nesting, $graph);
            }
        }

        return $instances;
    }
}
