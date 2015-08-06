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
	private $instance;

	function instantiate(Context $context, $nesting, $graph = null)
	{
		if (!isset($this->instance)) {
			$this->instance = parent::instantiate($context, $nesting, $graph);
		}

		return $this->instance;
	}
}