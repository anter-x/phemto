<?php
namespace phemto\lifecycle;

use phemto\Context;

/**
 * Creates a new object each time its required.
 *
 * @package phemto\lifecycle
 */
class Factory extends Lifecycle
{
	public function instantiate(Context $context, $nesting, $graph = null)
	{
		array_unshift($nesting, $this->class);

		$dependencies = $context->createDependencies($context->repository()->getConstructorParameters($this->class), $nesting, $graph);

		$instance = call_user_func_array(
			array(new \ReflectionClass($this->class), 'newInstance'),
			$dependencies
		);

        $context->invokeSetters($context, $nesting, $this->class, $instance, $graph);

        return $instance;
	}
}