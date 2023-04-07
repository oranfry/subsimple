<?php

namespace subsimple;

abstract class Thing
{
    protected static array $library = [];
    public string $name;

    public static function create(string $name): static
    {
        $class = strtolower(get_called_class()) . '\\' . $name;
        $thing = new $class();
        $thing->name = $name;

        return $thing;
    }

    public static function load(string $name): static
    {
        $class = strtolower(get_called_class()) . '\\' . $name;

        if (isset(static::$library[$class])) {
            return static::$library[$class];
        }

        $thing = static::create($name);

        static::$library[$class] = $thing;

        return $thing;
    }

    public static function rput(string $alias, $thing): void
    {
        if (!$thing instanceof static) {
            error_response('Object passed to ' . get_called_class() . '::rput is not a ' . get_called_class() . ' (is ' . get_class($thing) . ')', 500);
        }

        static::$register[$alias] = $thing;
    }

    public static function rget(string $alias): static
    {
        return @static::$register[$alias];
    }

    public static function rgetAll(): array
    {
        return static::$register;
    }
}
