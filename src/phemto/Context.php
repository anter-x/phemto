<?php
namespace phemto;

use phemto\exception\CannotFindImplementation;
use phemto\exception\MissingDependency;
use phemto\lifecycle\Factory;
use phemto\lifecycle\Lifecycle;
use phemto\lifecycle\Value;
use phemto\lifecycle\ConfigValue;
use phemto\repository\ClassRepository;


class Context
{
	/**
	 * @var Context|Phemto
	 */
	private $parent;

	/**
	 * A map of class name to lifecycle objects.
	 *
	 * @var Lifecycle[]
	 */
	private $registry = array();
	/**
	 * @var Variable[]
	 */
	private $variables = array();
	/**
	 * @var Context[]
	 */
	private $contexts = ['default' => []];
	/**
	 * @var Type[]
	 */
	private $types = array();

	private $wrappers = array();

	function __construct($parent)
	{
		$this->parent = $parent;
	}

	function willUse($preference)
	{
		if ($preference instanceof Lifecycle) {
			$lifecycle = $preference;
		} elseif (is_object($preference)) {
			$lifecycle = new Value($preference);
		} else {
			$lifecycle = new Factory($preference);
		}
		$this->registry[$lifecycle->class] = $lifecycle;
	}

	/**
     * @param string $name
     * @return Variable
     */
    function forVariable($name)
	{
		return $this->variables[$name] = new Variable($this);
	}

	/**
     * @param string $type
     * @param string $graph
     * @return self
     */
    function whenCreating($type, $graph = null)
	{
        $graph = $graph ?: 'default';
        
		if (!isset($this->contexts[$graph][$type])) {
			$this->contexts[$graph][$type] = new Context($this);
		}

		return $this->contexts[$graph][$type];
	}

	/**
     * @param string $type
     * @return Type
     */
    function forType($type)
	{
		if (!isset($this->types[$type])) {
			$this->types[$type] = new Type();
		}

		return $this->types[$type];
	}

	function wrapWith($type)
	{
		array_push($this->wrappers, $type);
	}

	function create($type, $nesting = array(), $graph = null)
	{
		$lifecycle = $this->pickFactory($type, $this->repository()->candidatesFor($type));
		$context = $this->determineContext($lifecycle->class, $graph);

		try {
			if ($wrapper = $context->hasWrapper($type, $nesting)) {
				array_unshift($nesting, $wrapper);
				return $this->create($wrapper, $nesting, $graph);
			}
			$instance = $lifecycle->instantiate($context, $nesting, $graph);
		} catch (MissingDependency $e) {
			$e->prependMessage("While creating $type: ");
			throw $e;
		}
		$this->invokeSetters($context, $nesting, $lifecycle->class, $instance, $graph);

		return $instance;
	}

	/**
	 * Pick a lifecycle for the given type from the available candidates.
	 *
	 * @param string   $type          type
	 * @param string[] $candidates    list of types that can satisfy this type
	 * @return Lifecycle              A lifecycle object for this type
	 *
	 * @throws exception\CannotFindImplementation
	 */
	public function pickFactory($type, $candidates)
	{
		if (count($candidates) == 0) {
			throw new CannotFindImplementation($type);
		} elseif ($preference = $this->preferFrom($candidates)) {
			return $preference;
		} else {
			return $this->parent->pickFactory($type, $candidates);
		}
	}

	function hasWrapper($type, $already_applied)
	{
		foreach ($this->wrappersFor($type) as $wrapper) {
			if (!in_array($wrapper, $already_applied)) {
				return $wrapper;
			}
		}

		return false;
	}

	private function invokeSetters(Context $context, $nesting, $class, $instance, $graph = null)
	{
		foreach ($context->settersFor($class) as $setter) {
			array_unshift($nesting, $class);

			$context->invoke(
				$instance,
				$setter,
				$context->createDependencies(
					$this->repository()->getParameters($class, $setter),
					$nesting,
                    $graph
				)
			);
		}
	}

	private function settersFor($class)
	{
		$setters = isset($this->types[$class]) ? $this->types[$class]->setters : array();

		return array_values(
			array_unique(
				array_merge(
					$setters,
					$this->parent->settersFor($class)
				)
			)
		);
	}

	function wrappersFor($type)
	{
		return array_values(
			array_merge(
				$this->wrappers,
				$this->parent->wrappersFor($type)
			)
		);
	}

	/**
	 * Call a method of an instance, automatically injecting parameters from the container.
	 *
	 * @param object  $instance         The class containing the method
	 * @param string  $method           The method name
	 * @param array $nesting            An array containing any nested dependencies.
	 * @return mixed The result of the call
	 */
	public function call($instance, $method, $nesting = [])
	{
		return $this->invoke(
			$instance,
			$method,
			$this->createDependencies(
				$this->repository()->getParameters(get_class($instance), $method),
				$nesting
			)
		);
	}

	function createDependencies($parameters, $nesting, $graph = null)
	{
		$values = array();
		foreach ($parameters as $parameter) {
			$values[] = $this->instantiateParameter($parameter, $nesting, $graph);
		}

		return $values;
	}

	/**
	 * @param \ReflectionParameter $parameter
	 * @param $nesting
	 * @return mixed|Value
	 */
	public function instantiateParameter($parameter, $nesting, $graph = null)
	{
		$hint = null;
		try {
			$hint = $parameter->getClass();
		} catch(\ReflectionException $e) {}

		try {
			if ($hint) {
				return $this->create($hint->getName(), $nesting, $graph);
			} elseif (isset($this->variables[$parameter->getName()])) {
                $preference = $this->variables[$parameter->getName()]->preference;
				if ($preference instanceof Lifecycle) {
                    $context = (empty($preference->class)) ? $this : $this->determineContext($preference->class, $graph);
					return $preference->instantiate($context, $nesting, $graph);
				} elseif ($preference instanceof ConfigValue) {
                    return $this->instantiateParameter($preference, $nesting, $graph)
                        ->get($preference->name);
                } elseif (!is_string($preference)) {
					return $preference;
				}

				return $this->create($preference, $nesting, $graph);
			}
		} catch (MissingDependency $e) {
			if($parameter->getClass()) {
				$e->prependMessage("While creating {$parameter->getClass()->getName()}: ");
			} else {
				$e->prependMessage("While creating {$parameter->getName()}: ");
			}
			throw $e;
		}

		return $this->parent->instantiateParameter($parameter, $nesting, $graph);
	}

	public function determineContext($class, $graph = null)
	{
        $graph = $graph ?: 'default';

        if (isset($this->contexts[$graph])) {
            foreach ($this->contexts[$graph] as $type => $context) {
                if ($this->repository()->isSupertype($class, $type)) {
                    return $context;
                }
            }
        }

        return $this->parent->determineContext($class, $graph);
	}

	private function invoke($instance, $method, $arguments)
	{
		return call_user_func_array(array($instance, $method), $arguments);
	}

	/**
	 * Picks the best candidate from a list.
	 *
	 * @param string[]  $candidates  A list of possible candidates
	 * @return null
	 */
	private function preferFrom($candidates)
	{
		foreach($candidates as $candidate) {
			if(isset($this->registry[$candidate])) {
				return $this->registry[$candidate];
			}
		}

		return false;
	}

	/**
	 * @return ClassRepository
	 */
	function repository()
	{
		return $this->parent->repository();
	}
}