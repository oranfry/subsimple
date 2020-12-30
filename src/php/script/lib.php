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
    return require search_plugins('src/php/controller/' . PAGE . '.php');
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
    return array_map(
        function ($o) use ($property) {
            return $o->{$property};
        },
        $objectArray
    );
}

function filter_objects($objectArray, $property, $cmp = 'exists', $value = null)
{
    return array_values(
        array_filter(
            $objectArray,
            function ($o) use ($property, $cmp, $value) {
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

                return false; //unsupported comparison
            }
        )
    );
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

