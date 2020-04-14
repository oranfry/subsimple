<?php
if (!defined('AUTHSCHEME')) {
    define('AUTHSCHEME', 'cookie');
}

if (!in_array(AUTHSCHEME, ['cookie', 'header'])) {
    error_log('AUTHSCHEME should be set to "cookie" or "header"');
    die();
}

function route()
{
    $path = strtok($_SERVER["REQUEST_URI"], '?');

    if (AUTHSCHEME == 'cookie' && preg_match(',^/$,', $path, $groups)) {
        require APP_HOME . '/src/php/script/login.php';

        die();
    }

    if (AUTHSCHEME == 'cookie' && @$_SESSION["AUTH"] != Config::get()->password) {
        header("Location: /");

        die();
    }

    if (AUTHSCHEME == 'header' && @getallheaders()['X-Auth'] != Config::get()->password) {
        error_response('Bad / missing auth header', 403);
    }

    if (!Router::match($path)) {
        error_response('Not found', 404);
    }

    if (!defined('PAGE') || !file_exists(APP_HOME . '/src/php/controller/' . PAGE . '.php')) {
        error_response('Not set up', 500);
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

function error_response($message, $code = 400)
{
    http_response_code($code);

    $error = $message;
    $layout = defined('LAYOUT') ? LAYOUT : 'main';

    require APP_HOME . '/src/php/layout/' . $layout . '-error.php';
    die();
}