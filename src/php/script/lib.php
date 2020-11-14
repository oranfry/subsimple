<?php
if (!defined('AUTHSCHEME')) {
    define('AUTHSCHEME', 'cookie');
}

if (!in_array(AUTHSCHEME, ['cookie', 'header', 'none'])) {
    error_log('AUTHSCHEME should be set to "cookie", "header" or "none"');
    die();
}

function route()
{
    global $argv;

    if (isset($argv)) {
        $command = array_shift($argv);
    }

    $path = isset($_SERVER["REQUEST_URI"]) ? strtok($_SERVER["REQUEST_URI"], '?') : implode(' ', $argv);

    if (AUTHSCHEME == 'cookie' && !@$_SESSION["AUTH"] && !preg_match(',^/$,', $path, $groups)) {
        header("Location: /");

        die();
    }

    if (!with_plugins(function($plugin_dir, $d) use (&$routerclass) {
        $routerfile = $plugin_dir . '/router.php';

        if (file_exists($routerfile)) {
            $routerclass = require $routerfile;
            return true;
        }
    })) {
        error_response('Router not specified');
    };

    if (!class_exists($routerclass)) {
        error_response('Specified router class does not exist');
    }

    $router = new $routerclass();

    if (!$router->match($path)) {
        error_response('Not found', 404);
    }

    if (!defined('PAGE') || !file_exists(APP_HOME . '/src/php/controller/' . PAGE . '.php')) {
        error_response('Not set up', 500);
    }

    if ((!defined('NOAUTH') || !NOAUTH) && AUTHSCHEME == 'header' && !@getallheaders()['X-Auth']) {
        error_response('Missing auth header', 403);
    }
}

function do_controller()
{
    return require APP_HOME . '/src/php/controller/' . PAGE . '.php';
}

function do_layout($viewdata)
{
    extract((array) $viewdata);

    require APP_HOME . '/src/php/layout/' . LAYOUT . '.php';
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

    $function($var);
    die('-');
}

function latest($type)
{
    $data = json_decode(file_get_contents(APP_HOME . '/build/latest.json'));

    return @$data->{$type} ?: 0;
}

