<?php

namespace phemto\lifecycle;

use phemto\Context;
use phemto\exception\MissingDependency;


class ReusedByParam extends Lifecycle
{
    protected $parameter;

    protected $instances = ['default' => []];

    public function __construct($class, $parameter)
    {
        parent::__construct($class);

        $this->parameter = $parameter;
    }

    public function instantiate(Context $context, $nesting, $graph = null)
	{
        $graph = $graph ?: 'default';

        $parameters = $this->getConstructorParameters($context);
        $values = $this->createOneDependency($context, $parameters, $nesting, $graph);
        $hash = (is_object($values[$this->parameter])) ? spl_object_hash($values[$this->parameter]) : (string) $values[$this->parameter];

        if (!isset($this->instances[$graph][$hash])) {
            $dependencies = array_values($this->createRestDependencies($context, $parameters, $values, $nesting, $graph));
             $instance = call_user_func_array(
                array(new \ReflectionClass($this->class), 'newInstance'),
                $dependencies
            );

            $context->invokeSetters($context, $nesting, $this->class, $instance, $graph);

            $this->instances[$graph][$hash] = $instance;
        }

        return $this->instances[$graph][$hash];
    }

    protected function getConstructorParameters($context)
    {
        $parameters = [];
        foreach ($context->repository()->getConstructorParameters($this->class) as $parameter) {
            $parameters[$parameter->getName()] = $parameter;
        }

        if (!isset($parameters[$this->parameter])) {
            throw new MissingDependency();
        }

        return $parameters;
    }

    protected function createOneDependency($context, $parameters, $nesting, $graph)
    {
        $values = [];
		foreach ($parameters as $parameter) {
            if ($parameter->getName() == $this->parameter) {
                $values[$parameter->getName()] = $context->instantiateParameter($parameter, $nesting, $graph);
            } else {
                $values[$parameter->getName()] = null;
            }
		}
		return $values;
    }

    protected function createRestDependencies($context, $parameters, $values, $nesting, $graph = null)
	{
		foreach ($parameters as $parameter) {
            if ($parameter->getName() != $this->parameter) {
                $values[$parameter->getName()] = $context->instantiateParameter($parameter, $nesting, $graph);
            }
		}
		return $values;
	}
}
