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

function parse_property_subexpression($property)
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
        error_response(__METHOD__ . ': invalid property expression');
    }

    return $parts;
}

function property_subexpression_exists(object $o, string $property)
{
    return property_subexpression_value($o, $property, true);
}

function property_subexpression_value(object $o, string $property, bool $existence_check = false)
{
    $return = &$o;

    foreach (parse_property_subexpression($property) as $part) {
        if ($part->type == 'object') {
            if (!is_object($return)) {
                error_response(__METHOD__ . ': not an object');
            }

            if (!in_array($part->prop, array_keys(get_object_vars($return)))) {
                if ($existence_check) {
                    return false;
                }

                if ($part->safely) {
                    return null;
                }

                error_response(__METHOD__ . ': object property does not exist: ' . $part->prop);
            }

            if (is_array($return->{$part->prop})) {
                $return = &$return->{$part->prop};
            } else {
                $return = $return->{$part->prop};
            }
        } else {
            if (!is_array($return)) {
                error_response(__METHOD__ . ': not an array');
            }

            if (!array_key_exists($part->prop, $return)) {
                if ($existence_check) {
                    return false;
                }

                if ($part->safely) {
                    return null;
                }

                error_response(__METHOD__ . ': array key does not exist: ' . $part->prop);
            }

            if (is_array($return[$part->prop])) {
                $return = &$return[$part->prop];
            } else {
                $return = $return[$part->prop];
            }
        }
    }

    if ($existence_check) {
        return true;
    }

    return $return;
}

function property_expression_exists(object $o, string $expression)
{
    foreach (property_expression_subs($expression) as $property) {
        if (!property_subexpression_value($o, $property, true)) {
            return false;
        }
    }

    return true;
}

function property_expression_value(object $o, string $expression, bool $existence_check = false)
{
    $result = null;

    foreach (property_expression_subs($expression) as $property) {
        $value = property_subexpression_value($o, $property, $existence_check);

        if ($result === null) {
            $result = $value;
        } else {
            $result .= $value;
        }
    }

    return $result;
}

function property_expression_subs($expression)
{
    return array_map('trim', explode('.', $expression));
}

function map_objects($objectArray, $property)
{
    $callback = function ($o) use ($property) {
        return property_expression_value($o, $property);
    };

    return array_map($callback, $objectArray);
}

function map_array($arrayArray, $property)
{
    $callback = function ($e) use ($property) {
        return $e[$property];
    };

    return array_map($callback, $arrayArray);
}

function filter_objects($objectArray, $property, $cmp = 'exists', $value = null, $value_is_expression = false)
{
    return array_values(array_filter($objectArray, function ($o) use ($property, $cmp, $value, $value_is_expression) {
        if (!is_object($o)) {
            error_response(__METHOD__ . ': encountered non-object');
        }

        if ($value_is_expression) {
            $value = property_expression_exists($o, $value) ? property_expression_value($o, $value) : null;
        }

        if (!property_expression_exists($o, $property)) {
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

            if ($cmp == 'matches') {
                return preg_match($value, '');
            }

            if ($cmp == 'truthy') {
                return false;
            }

            if ($cmp == 'falsy') {
                return true;
            }

            if ($cmp == 'lt') {
                return strcmp('', $value) < 0;
            }

            if ($cmp == 'gt') {
                return strcmp('', $value) > 0;
            }

            error_response(__METHOD__ . ': unsupported comparison');
        }

        if ($cmp == 'exists') {
            return true;
        }

        if ($cmp == 'notexists') {
            return false;
        }

        $resolved = property_expression_value($o, $property);

        if ($cmp == 'is') {
            return $resolved == $value;
        }

        if ($cmp == 'not') {
            return $resolved != $value;
        }

        if ($cmp == 'in') {
            return in_array($resolved, $value);
        }

        if ($cmp == 'notin') {
            return !in_array($resolved, $value);
        }

        if ($cmp == 'null') {
            return is_null($resolved);
        }

        if ($cmp == 'notnull') {
            return !is_null($resolved);
        }

        if ($cmp == 'matches') {
            return preg_match($value, $resolved);
        }

        if ($cmp == 'truthy') {
            return (bool) $resolved;
        }

        if ($cmp == 'falsy') {
            return !(bool) $resolved;
        }

        if ($cmp == 'lt') {
            return strcmp($resolved, $value) < 0;
        }

        if ($cmp == 'gt') {
            return strcmp($resolved, $value) > 0;
        }

        error_response(__METHOD__ . ': unsupported comparison');
    }));
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

function object_int_comparator(string $property, bool $desc = false)
{
    return function (object $a, object $b) use ($property, $desc) {
        return ($desc ? -1 : 1) * ((int) property_expression_value($a, $property) <=> (int) property_expression_value($b, $property));
    };
}

function object_string_comparator(string $property, bool $desc = false)
{
    return function (object $a, object $b) use ($property, $desc) {
        return ($desc ? -1 : 1) * ((string) property_expression_value($a, $property) <=> (string) property_expression_value($b, $property));
    };
}

function object_filter(string $property, string $cmp = 'exists', $value = null, bool $value_is_expression = false)
{
    return function (object $o) use ($property, $cmp, $value, $value_is_expression) {
        return property_expression_value($o, $property, $cmp, $value, $value_is_expression);
    };
}
