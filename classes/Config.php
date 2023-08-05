<?php

namespace subsimple;

final class Config
{
    private static $instance = null;

    public static function set($config)
    {
        if (static::$instance !== null) {
            throw new Exception('Only one config allowed');
        }

        static::$instance = $config;
    }

    public static function get()
    {
        return static::$instance;
    }
}
