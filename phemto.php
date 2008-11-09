<?php
class CannotFindImplementation extends Exception { }
class CannotDetermineImplementation extends Exception { }
class SetterDoesNotExist extends Exception { }
class MissingDependency extends Exception { }

class Phemto {
    private $top;
    
    function __construct() {
        $this->top = new Scope($this);
    }
    
    function willUse($preference) {
        $this->top->willUse($preference);
    }
    
    function forVariable($name) {
        return $this->top->forVariable($name);
    }
    
    function whenCreating($type) {
        return $this->top->whenCreating($type);
    }
    
    function call($method) {
        $this->top->call($method);
    }
    
    function create($type) {
        $this->repository = new ClassRepository();
        return $this->top->create($type);
    }
    
    function pick($candidates) {
        throw new CannotDetermineImplementation();
    }
    
    function settersFor($class) {
        return array();
    }

    function instantiateParameter($parameter) {
        throw new MissingDependency();
    }
    
    function repository() {
        return $this->repository;
    }
}

class Scope {
    private $parent;
    private $repository;
    private $registry = array();
    private $variables = array();
    private $scopes = array();
    private $setters = array();

    function __construct($parent) {
        $this->parent = $parent;
    }

    function willUse($preference) {
        $lifecycle = $preference instanceof Lifecycle ? $preference : new Factory($preference);
        array_unshift($this->registry, $lifecycle);
    }

    function forVariable($name) {
        return $this->variables[$name] = new Variable();
    }

    function whenCreating($type) {
        return $this->scopes[$type] = new Scope($this);
    }
    
    function call($method) {
        array_unshift($this->setters, $method);
    }
    
    function wrapWith($decorator) {
    }
    
    function create($type) {
        $lifecycle = $this->pick($this->repository()->candidatesFor($type));
        $scope = $this->determineScope($lifecycle->class);
        $instance = $lifecycle->instantiate($scope->createDependencies(
                        $this->repository()->getConstructorParameters($lifecycle->class)));
        foreach ($scope->settersFor($lifecycle->class) as $setter) {
            $scope->invoke($instance, $setter, $scope->createDependencies(
                                $this->repository()->getParameters($lifecycle->class, $setter)));
        }
        return $instance;
    }
    
    function pick($candidates) {
        if (count($candidates) == 0) {
            throw new CannotFindImplementation();
        } elseif ($preference = $this->preferFrom($candidates)) {
            return $preference;
        } elseif (count($candidates) == 1) {
            return new Factory($candidates[0]);
        } else {
            return $this->parent->pick($candidates);
        }
    }
    
    function settersFor($class) {
        return array_values(array_unique(array_merge(
                    $this->setters, $this->parent->settersFor($class))));
    }
    
    function createDependencies($parameters) {
        return array_map(array($this, 'instantiateParameter'), $parameters);
    }
    
    function instantiateParameter($parameter) {
        try {
            if ($hint = $parameter->getClass()) {
                return $this->create($hint->getName());
            }
        } catch (Exception $e) {
            if (isset($this->variables[$parameter->getName()])) {
                return $this->create($this->variables[$parameter->getName()]->interface);
            }
        }
        return $this->parent->instantiateParameter($parameter);
    }
    
    private function determineScope($class) {
        foreach ($this->scopes as $type => $scope) {
            if ($this->repository()->inScope($class, $type)) {
                return $scope;
            }
        }
        return $this;
    }
    
    private function invoke($instance, $method, $arguments) {
        call_user_func_array(array($instance, $method), $arguments);
    }
    
    private function preferFrom($candidates) {
        foreach ($this->registry as $preference) {
            if ($preference->isOneOf($candidates)) {
                return $preference;
            }
        }
        return false;
    }
    
    function repository() {
        return $this->parent->repository();
    }
}

class Variable {
    public $interface;

    function willUse($interface) {
        $this->interface = $interface;
    }
}

abstract class Lifecycle {
    public $class;

	function __construct($class) {
		$this->class = $class;
	}
    
    function isOneOf($candidates) {
        return in_array($this->class, $candidates);
    }

    abstract function instantiate($dependencies);
}

class Factory extends Lifecycle {
	function instantiate($dependencies) {
		return call_user_func_array(
				array(new ReflectionClass($this->class), 'newInstance'),
				$dependencies);
	}
}

class Singleton extends Lifecycle {
    private $instance;
    
	function instantiate($dependencies) {
        if (! isset($this->instance)) {
            $this->instance = call_user_func_array(
                    array(new ReflectionClass($this->class), 'newInstance'),
                    $dependencies);
        }
        return $this->instance;
	}
}

class Sessionable extends Lifecycle {
    private $slot;
    
    function __construct($slot, $class) {
        $this->slot = $slot;
        parent::__construct($class);
    }
    
	function instantiate($dependencies) {
        if (! isset($_SESSION[$this->slot])) {
            $_SESSION[$this->slot] = call_user_func_array(
                    array(new ReflectionClass($this->class), 'newInstance'),
                    $dependencies);
        }
        return $_SESSION[$this->slot];
	}
}

class ClassRepository {
    private static $reflection = false;
    
    function __construct() {
        if (! self::$reflection) {
            self::$reflection = new ReflectionCache();
        }
        self::$reflection->refresh();
    }
    
    function candidatesFor($interface) {
        return array_merge(
                self::$reflection->concreteSubgraphOf($interface),
                self::$reflection->implementationsOf($interface));
    }
    
    function inScope($class, $type) {
        $supertypes = array_merge(
                array($class),
                self::$reflection->interfacesOf($class),
                self::$reflection->parentsOf($class));
        return in_array($type, $supertypes);
    }
    
    function getConstructorParameters($class) {
        $reflection = self::$reflection->reflection($class);
        if ($constructor = $reflection->getConstructor()) {
            return $constructor->getParameters();
        }
        return array();
    }
    
    function getParameters($class, $method) {
        $reflection = self::$reflection->reflection($class);
        if (! $reflection->hasMethod($method)) {
            throw new SetterDoesNotExist();
        }
        return $reflection->getMethod($method)->getParameters();
    }
}

class ReflectionCache {
    private $implementations_of = array();
    private $interfaces_of = array();
    private $reflections = array();
    private $subclasses = array();
    private $parents = array();
    
    function refresh() {
        $this->buildIndex(array_diff(get_declared_classes(), $this->indexed()));
        $this->subclasses = array();
    }
    
    function implementationsOf($interface) {
        return isset($this->implementations_of[$interface]) ?
                $this->implementations_of[$interface] : array();
    }
    
    function interfacesOf($class) {
        return isset($this->interfaces_of[$class]) ?
                $this->interfaces_of[$class] : array();
    }
    
    function concreteSubgraphOf($class) {
        if (! class_exists($class)) {
            return array();
        }
        if (! isset($this->subclasses[$class])) {
            $this->subclasses[$class] = $this->isConcrete($class) ? array($class) : array();
            foreach ($this->indexed() as $candidate) {
                if (is_subclass_of($candidate, $class) && $this->isConcrete($candidate)) {
                    $this->subclasses[$class][] = $candidate;
                }
            }
        }
        return $this->subclasses[$class];
    }
    
    function parentsOf($class) {
        if (! isset($this->parents[$class])) {
            $this->parents[$class] = class_parents($class);
        }
        return $this->parents[$class];
    }
    
    function reflection($class) {
        if (! isset($this->reflections[$class])) {
            $this->reflections[$class] = new ReflectionClass($class);
        }
        return $this->reflections[$class];
    }
    
    private function isConcrete($class) {
        return ! $this->reflection($class)->isAbstract();
    }
    
    private function indexed() {
        return array_keys($this->interfaces_of);
    }
    
    private function buildIndex($classes) {
        foreach ($classes as $class) {
            $interfaces = class_implements($class);
            $this->interfaces_of[$class] = $interfaces;
            foreach ($interfaces as $interface) {
                $this->crossReference($interface, $class);
            }
        }
    }
    
    private function crossReference($interface, $class) {
        if (! isset($this->implementations_of[$interface])) {
            $this->implementations_of[$interface] = array();
        }
        $this->implementations_of[$interface][] = $class;
        $this->implementations_of[$interface] =
                array_values(array_unique($this->implementations_of[$interface]));
    }
}
?>