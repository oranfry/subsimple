<?php
abstract class Thing
{
    protected static $library = [];
    public $name;

    public static function create($name)
    {
        $class = strtolower(get_called_class()) . '\\' . $name;
        $thing = new $class();
        $thing->name = $name;

        return $thing;
    }

    public static function load($name)
    {
        if (!is_string($name)) {
            error_response('Name passed to Thing::load was not a string', 500);
        }

        $class = strtolower(get_called_class()) . '\\' . $name;

        if (isset(static::$library[$class])) {
            return static::$library[$class];
        }

        $thing = static::create($name);

        static::$library[$class] = $thing;

        return $thing;
    }

    public static function rput($alias, $thing)
    {
        if (!property_exists(get_called_class(), 'register') || !is_array(static::$register)) {
            error_response(get_called_class() . ' does not have a register', 500);
        }

        if (!is_string($alias)) {
            error_response('Alias passed to ' . get_called_class() . '::rput was not a string', 500);
        }

        if (!$thing instanceof static) {
            error_response('Object passed to ' . get_called_class() . '::rput is not a ' . get_called_class() . ' (is ' . get_class($thing) . ')', 500);
        }

        static::$register[$alias] = $thing;
    }

    public static function rget($alias)
    {
        if (!is_string($alias)) {
            error_response('Alias passed to ' . get_called_class() . '::rget was not a string', 500);
        }

        return @static::$register[$alias];
    }

    public static function rgetAll()
    {
        return static::$register;
    }
}
