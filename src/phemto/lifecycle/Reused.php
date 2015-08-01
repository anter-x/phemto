<?php
namespace phemto\lifecycle;

use phemto\Context;

/**
 * Factory + Singleton lifecycle provider.
 *
 * @package phemto\lifecycle
 */
class Reused extends Factory
{
	private $instance = ['default' => null];

	function instantiate(Context $context, $nesting, $graph = null)
	{
        $graph = $graph ?: 'default';

		if (!isset($this->instance[$graph])) {
			$this->instance[$graph] = parent::instantiate($context, $nesting, $graph);
		}

		return $this->instance[$graph];
	}
}