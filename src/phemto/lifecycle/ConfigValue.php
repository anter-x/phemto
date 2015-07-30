<?php

namespace phemto\lifecycle;


class ConfigValue
{
    const CONFIG_NAME = '__app_config';

    public $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return self::CONFIG_NAME;
    }

    public function getClass()
    {
        return null;
    }
}
