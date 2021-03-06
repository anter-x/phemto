<?php
namespace phemto\exception;

/**
 * Thrown when a dependency cannot be resolved
 *
 * @package phemto\exception
 */
class MissingDependency extends \Exception {
	public function prependMessage($msg){
		$this->message = $msg . $this->message;
	}
}