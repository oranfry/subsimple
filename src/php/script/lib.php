<?php

use subsimple\Config;

function route()
{
    global $argv;

    if (isset($argv)) {
        $command = array_shift($argv);
    }

    $path = isset($_SERVER["REQUEST_URI"]) ? strtok($_SERVER["REQUEST_URI"], '?') : implode(' ', $argv);

    if (!class_exists($routerclass = @Config::get()->router)) {
        error_response('Specified router class [' . $routerclass . '] does not exist');
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
    $data = json_decode(file_get_contents(APP_HOME . '/latest.json'));

    return @$data->{$type} ?: 0;
}
