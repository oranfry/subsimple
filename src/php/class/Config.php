<?php
final class Config
{
    private static $instance = null;

    public static function set($config)
    {
        if (static::$instance !== null) {
            die('Only one config allowed');
        }

        static::$instance = $config;
    }

    public static function replace($config)
    {
        static::$instance = null;
        static::set($config);
    }

    public static function get()
    {
        return static::$instance;
    }
}
