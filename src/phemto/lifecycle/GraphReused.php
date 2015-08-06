<?php
namespace phemto\lifecycle;

use phemto\Context;

/**
 * Factory + Singleton lifecycle provider.
 *
 * @package phemto\lifecycle
 */
class GraphReused extends Factory
{
	protected $graph;

    protected $instance = ['default' => null];

    public function __construct($class, $graph = null)
    {
        parent::__construct($class);
        $this->graph = $graph;
    }

	public function instantiate(Context $context, $nesting, $graph = null)
	{
        $graph = $this->graph ?: ($graph ?: 'default');

		if (!isset($this->instance[$graph])) {
			$this->instance[$graph] = parent::instantiate($context->determineContext($this->class, $graph), $nesting, $graph);
		}

		return $this->instance[$graph];
	}
}