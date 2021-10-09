<?php
function route()
{
    global $argv;

    if (isset($argv)) {
        $command = array_shift($argv);
    }

    $path = isset($_SERVER["REQUEST_URI"]) ? strtok($_SERVER["REQUEST_URI"], '?') : implode(' ', $argv);

    $routerclass = @Config::get()->router;

    if (!class_exists($routerclass)) {
        error_response('Specified router class does not exist');
    }

    $router = new $routerclass();

    if (!$router->match($path)) {
        error_response("Not found: {$path}", 404);
    }

    if (!defined('PAGE')) {
        error_response('Not set up (1)', 500);
    }

    if (!search_plugins('src/php/controller/' . PAGE . '.php')) {
        error_response('Not set up (2): ' . PAGE, 500);
    }

    if (!defined('AUTHSCHEME')) {
        define('AUTHSCHEME', 'cookie');
    }

    if (!in_array(AUTHSCHEME, ['cookie', 'header', 'onetime', 'none', 'deny'])) {
        error_log('AUTHSCHEME should be set to "cookie", "header", "onetime", "none", or "deny"');
        die();
    }

    with_plugins(function($dir, $name) {
        $init_func = 'postroute_' . ($name ?? 'app');

        if (function_exists($init_func)) {
            $init_func();
        }
    });
}

function do_controller()
{
    return (function () {
        $_controller_data = [];

        // app controller

        if (is_file($_app_controller = APP_HOME . '/src/php/controller/app.php')) {
            $_app_controller_data = require $_app_controller;

            if (!is_array($_app_controller_data)) {
                error_response('app controller should return an array');
            }

            $_controller_data = $_app_controller_data;
        }

        // page controller

        $_page_controller_data = require search_plugins('src/php/controller/' . PAGE . '.php');

        if (!is_array($_page_controller_data)) {
            error_response('page controller should return an array');
        }

        $_controller_data = array_merge($_controller_data, $_page_controller_data);

        return $_controller_data;
    })();
}

function do_layout($viewdata)
{
    $layout_file = null;

    $result = with_plugins(function($dir, $name) use (&$layout_file) {
        $_layout_file = $dir . '/src/php/layout/' . LAYOUT . '.php';

        if (file_exists($_layout_file) && !is_dir($_layout_file)) {
            $layout_file = $_layout_file;
            return true;
        }
    });

    if (!$result) {
        error_log('Could not find a layout called "' . LAYOUT . '"');
        error_response('Internal server error');
    }

    extract((array) $viewdata);

    require $layout_file;
}

function map_objects($objectArray, $property)
{
    if (strpos($property, '->') !== 0 && strpos($property, '[') !== 0) {
        $property = '->' . $property;
    }

    $parts = [];
    $matches = null;

    while (preg_match('/^(->)(@)?([^[>]+)/', $property, $matches) || preg_match('/^(\[)(@)?([^[]+)\]/', $property, $matches)) {
        if (substr($property, strlen($matches[0]) - 1, 2) == '->') {
            $matches[0] = substr($matches[0], 0, strlen($matches[0]) - 1);
            $matches[3] = substr($matches[3], 0, strlen($matches[3]) - 1);
        }

        $parts[] = (object) [
            'type' => $matches[1] == '->' ? 'object' : 'array',
            'prop' => $matches[3],
            'safely' => $matches[2] == '@',
        ];

        $property = substr($property, strlen($matches[0]));
    }

    if ($property) {
        error_response('map_objects: invalid property expression');
    }

    return array_map(
        function ($o) use ($parts) {
            $return = &$o;

            foreach ($parts as $part) {
                if ($part->type == 'object') {
                    if (!is_object($return)) {
                        error_response('map_objects: not an object');
                    }

                    if (!in_array($part->prop, array_keys(get_object_vars($return)))) {
                        if ($part->safely) {
                            return null;
                        }

                        error_response('map_objects: object property does not exist: ' . $part->prop);
                    }

                    if (is_array($return->{$part->prop})) {
                        $return = &$return->{$part->prop};
                    } else {
                        $return = $return->{$part->prop};
                    }
                } else {
                    if (!is_array($return)) {
                        error_response('map_objects: not an array');
                    }

                    if (!array_key_exists($part->prop, $return)) {
                        if ($part->safely) {
                            return null;
                        }

                        error_response('map_objects: array key does not exist: ' . $part->prop);
                    }

                    if (is_array($return[$part->prop])) {
                        $return = &$return[$part->prop];
                    } else {
                        $return = $return[$part->prop];
                    }
                }
            }

            return $return;
        },
        $objectArray
    );
}

function map_array($arrayArray, $property)
{
    return array_map(
        function ($e) use ($property) {
            return $e[$property];
        },
        $arrayArray
    );
}

function filter_objects($objectArray, $property, $cmp = 'exists', $value = null)
{
    return array_values(
        array_filter(
            $objectArray,
            function ($o) use ($property, $cmp, $value) {
                if (!is_object($o)) {
                    error_response('non object given to filter_objects()');
                }

                if (!property_exists($o, $property)) {
                    if ($cmp == 'notexists') {
                        return true;
                    }

                    if ($cmp == 'exists') {
                        return false;
                    }

                    if ($cmp == 'is') {
                        return !$value;
                    }

                    if ($cmp == 'not') {
                        return (bool) $value;
                    }

                    if ($cmp == 'in') {
                        return in_array('', $value) || in_array(null, $value);
                    }

                    if ($cmp == 'notin') {
                        return !in_array('', $value) || in_array(null, $value);
                    }

                    if ($cmp == 'null') {
                        return true;
                    }

                    if ($cmp == 'notnull') {
                        return false;
                    }

                    return false; //unsupported comparison
                }

                if ($cmp == 'exists') {
                    return true;
                }

                if ($cmp == 'notexists') {
                    return false;
                }

                if ($cmp == 'is') {
                    return $o->{$property} == $value;
                }

                if ($cmp == 'not') {
                    return $o->{$property} != $value;
                }

                if ($cmp == 'in') {
                    return in_array($o->{$property}, $value);
                }

                if ($cmp == 'notin') {
                    return !in_array($o->{$property}, $value);
                }

                if ($cmp == 'null') {
                    return is_null($o->{$property});
                }

                if ($cmp == 'notnull') {
                    return !is_null($o->{$property});
                }

                return false; //unsupported comparison
            }
        )
    );
}

function find_objects($objectArray, $property, $cmp = 'exists', $values = [])
{
    return array_map(function($value) use ($objectArray, $property, $cmp) {
        return find_object($objectArray, $property, $cmp, $value);
    }, $values);
}

function find_object($objectArray, $property, $cmp = 'exists', $value = null)
{
    $found = filter_objects($objectArray, $property, $cmp, $value);

    return reset($found);
}

function remove_object(&$objectArray, $property, $cmp = 'exists', $value = null)
{
    foreach ($objectArray as $key => $object) {
        if ($removed = find_object([$object], $property, $cmp, $value)) {
            unset($objectArray[$key]);

            return $removed;
        }
    }

    return false;
}

function indicies_of_objects($objectArray, $property, $cmp = 'exists', $values = [])
{
    return array_map(function($value) use ($objectArray, $property, $cmp) {
        return index_of_object($objectArray, $property, $cmp = 'exists', $value);
    }, $values);
}

function index_of_object($objectArray, $property, $cmp = 'exists', $value = null)
{
    foreach ($objectArray as $index => $object) {
        if (count(filter_objects([$object], $property, $cmp, $value))) {
            return $index;
        }
    }
}

function date_shift($date, $offset)
{
    return date('Y-m-d', strtotime($offset, strtotime($date)));
}

function var_die($var)
{
    $function = implode('_', ['var', 'dump']);

    if (PHP_SAPI != 'cli') {
        echo '<pre>';
    }

    $function($var);
    die('-');
}

function latest($type)
{
    $data = json_decode(file_get_contents(APP_HOME . '/build/latest.json'));

    return @$data->{$type} ?: 0;
}

