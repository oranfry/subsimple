<?php

use subsimple\Config;

function dd()
{
    call_user_func_array('var_dump', func_get_args());

    die();
}

function ddj()
{
    if (php_sapi_name() == 'cli') {
        $wrap = 'identity';
    } else {
        $wrap = 'htmlspecialchars';
        echo '<pre>';
    }

    foreach (func_get_args() as $i => $data) {
        if ($i) {
            echo "\n----------\n";
        }

        echo $wrap(json_encode($data, JSON_PRETTY_PRINT));
    }

    die();
}

function nprint($something)
{
    return !print($something);
}

function error_response($message, int $code = null)
{
    $code ??= (php_sapi_name() == 'cli' ? 1 : 400);

    if (!is_string($message)) {
        $message = var_export($message, true);
    }

    $error = $message;
    $layout = defined('LAYOUT') ? LAYOUT : 'main';

    if (php_sapi_name() != 'cli') {
        http_response_code($code);
        error_log("{$code} {$message}");

        foreach (debug_backtrace() as $trace) {
            $location_description = implode(':', array_filter([@$trace['file'], @$trace['line']]));

            if (@$trace['function']) {
                $location_description .= ($location_description ? ' ': null) .  '(' . $trace['function'] . ')';
            }

            error_log($location_description);
        }
    }

    while (ob_get_level()) {
        ob_end_clean();
    }

    if (file_exists($layout_file = search_plugins_for_error_layout($layout))) {
        require $layout_file;
    } else {
        echo "An error occurred but we are unable to display any details\n";
    }

    if (php_sapi_name() == 'cli') {
        die($code ?? 1);
    }

    die();
}

function identity($argument)
{
    return $argument;
}

function init_plugins()
{
    with_plugins(function($dir, $name) {
        $init_func = 'init_' . ($name ?? 'app');

        if (function_exists($init_func)) {
            $init_func();
        }
    });
}

function deinit_plugins()
{
    with_plugins(function($dir, $name) {
        $init_func = 'deinit_' . ($name ?? 'app');

        if (function_exists($init_func)) {
            $init_func();
        }
    });
}

function load_plugin_libs()
{
    with_plugins(function($dir, $name) {
        @include $dir . '/src/php/script/lib.php';
    });
}

function search_plugins($file, &$plugin_dir = null)
{
    $found = null;

    with_plugins(function($dir, $name) use ($file, &$found, &$plugin_dir) {
        $_filepath = $dir . '/' . $file;

        if (file_exists($_filepath)) {
            $found = $_filepath;
            $plugin_dir = $dir;

            return true;
        }
    });

    return $found;
}

function search_plugins_for_controller($name, &$plugin_dir = null)
{
    return search_plugins('src/php/controller/' . $name . '.php', $plugin_dir);
}

function search_plugins_for_error_layout($name, &$plugin_dir = null)
{
    $fallback = 'fallback' . (php_sapi_name() == 'cli' ? '-cli' : null);

    return search_plugins('src/php/error/' . $name . '.php', $plugin_dir) ?? search_plugins("src/php/error/$fallback.php", $plugin_dir);
}

function search_plugins_for_layout($name, &$plugin_dir = null)
{
    return search_plugins('src/php/layout/' . $name . '.php', $plugin_dir);
}

function ss_capture($file, array $viewdata = [], &$return_value = null)
{
    ob_start();

    $return_value = ss_require($file, $viewdata);

    return ob_get_clean();
}

function ss_include($file, array $viewdata = [])
{
    if (strpos($file, '/') === 0) {
        $resolved = $file;
    } elseif (!($resolved = search_plugins($file))) {
        return null;
    }

    extract($viewdata, EXTR_REFS);

    return require $resolved;
}

function ss_require($file, array $viewdata = [])
{
    if (strpos($file, '/') === 0) {
        $resolved = $file;
    } elseif (!($resolved = search_plugins($file))) {
        error_response('Could not find required file within any plugin: [' . $file . ']');
    }

    extract($viewdata, EXTR_REFS);

    return require $resolved;
}

function value($something)
{
    if (is_callable($something)) {
        return ($something)();
    }

    return $something;
}

function with_plugins($callback)
{
    // "base" plugin

    if ($callback(APP_HOME, null)) {
        return true;
    }

    if (defined('PLUGINS')) {
        foreach (PLUGINS as $plugin_dir) {
            if ($callback($plugin_dir, basename($plugin_dir))) {
                return true;
            }
        }
    }

    return false;
}

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
        error_response('Could not determine page', 500);
    }

    if (!search_plugins_for_controller(PAGE)) {
        error_response('Missing controller for page: ' . PAGE, 500);
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
        $page_controller = search_plugins_for_controller(PAGE, $_plugin_dir);
        $controller_data = compact('_plugin_dir');

        // plugin controllers

        with_plugins(function ($plugin_dir) use (&$controller_data) {
            if (!is_file($plugin_controller = $plugin_dir . '/src/php/controller/plugin.php')) {
                return;
            }

            $plugin_controller_data = ss_require($plugin_controller, $controller_data);

            if (!is_array($plugin_controller_data)) {
                error_response('plugin controller should return an array');
            }

            $controller_data = array_merge($controller_data, $plugin_controller_data);
        });

        // app controller

        if (is_file($_app_controller = APP_HOME . '/src/php/controller/app.php')) {
            $app_controller_data = ss_require($_app_controller, $controller_data);

            if (!is_array($app_controller_data)) {
                error_response('app controller should return an array');
            }

            $controller_data = array_merge($controller_data, $app_controller_data);
        }

        // page controller

        $page_controller_data = ss_require($page_controller, $controller_data);

        if (!is_array($page_controller_data)) {
            error_response('page controller should return an array');
        }

        $controller_data = array_merge($controller_data, $page_controller_data);

        return $controller_data;
    })();
}

function do_layout($viewdata)
{
    if (!$layout_file = search_plugins_for_layout(LAYOUT)) {
        error_response('Could not find a layout called "' . LAYOUT . '"');
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

