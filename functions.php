<?php

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

function error_response($message, $code = null, $info = [])
{
    if ($code === null) {
        $code = php_sapi_name() == 'cli' ? 1 : 400;
    }

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

        die(php_sapi_name() == 'cli' ? ($code ?? 1) : null);
    }

    echo "An error occurred but we are unable to display any details\n";

    die(php_sapi_name() == 'cli' ? ($code ?? 1) : null);
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

function ss_include($file)
{
    if ($resolved = search_plugins($file)) {
        return require $resolved;
    }

    return null;
}

function ss_require($file)
{
    if (!($resolved = search_plugins($file))) {
        error_response('Could not find required file within any plugin: [' . $file . ']');
    }

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
