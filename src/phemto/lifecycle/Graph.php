<?php

namespace phemto\lifecycle;

use phemto\Context;


class Graph extends Factory
{
    protected $graph;

    public function __construct($class, $graph)
    {
        parent::__construct($class);
        $this->graph = $graph;
    }

    public function instantiate(Context $context, $nesting, $graph = null)
	{
        return parent::instantiate($context->determineContext($this->class, $this->graph), $nesting, $this->graph);
	}
}
