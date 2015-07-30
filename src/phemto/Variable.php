<?php
namespace phemto;

use phemto\lifecycle\Value;
use phemto\lifecycle\ConfigValue;

/**
 * Represents a named variable. Allows injecting dependencies by name
 *
 * @package phemto
 */
class Variable
{
	/**
	 * @var Value
	 */
	public $preference;

	/**
	 * @var Context
	 */
	private $context;

	function __construct($context)
	{
		$this->context = $context;
	}

	function willUse($preference)
	{
		$this->preference = $preference;

		return $this->context;
	}

	/**
	 * @param $string
	 * @return Value
	 */
	function useString($string)
	{
		$this->preference = new Value($string);

		return $this->context;
	}

    public function useConfig($string)
    {
        $this->preference = new ConfigValue($string);

        return $this->context;
    }
}