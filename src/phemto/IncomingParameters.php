<?php
namespace phemto;

/**
 * Allows named parameters to be specified when calling a method of a class but still have di.
 * @package phemto
 */
class IncomingParameters
{
	private $injector;

	/**
	 * @param array $names
	 * @param Phemto $injector
	 */
	function __construct($names, $injector)
	{
		$this->names = $names;
		$this->injector = $injector;
	}

	/**
     * @return Phemto
     */
    function with()
	{
		$values = func_get_args();
		$this->injector->useParameters(array_combine($this->names, $values));

		return $this->injector;
	}
}