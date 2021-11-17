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

function define_autoloader()
{
    spl_autoload_register(function ($class_name) {
        $class_translated = str_replace('\\', '/', $class_name);

        $result = with_plugins(function($dir, $name) use ($class_translated) {
            $file = "{$dir}/src/php/class/{$class_translated}.php";

            if (file_exists($file)) {
                require $file;
                return true;
            }
        });

        if (!$result) {
            error_response("Could not autoload class {$class_name}", 500);
        }
    });
}

function error_response($message, $code = null, $info = [])
{
    if ($code === null) {
        $code = php_sapi_name() == 'cli' ? 1 : 400;
    }

    if (php_sapi_name() != 'cli') {
        http_response_code($code);
    }

    if (!is_string($message)) {
        $message = var_export($message, true);
    }

    $error = $message;
    $layout = defined('LAYOUT') ? LAYOUT : 'main';

    error_log("{$code} {$message}");

    foreach (debug_backtrace() as $trace) {
        $location_description = implode(':', array_filter([@$trace['file'], @$trace['line']]));

        if (@$trace['function']) {
            $location_description .= ($location_description ? ' ': null) .  '(' . $trace['function'] . ')';
        }

        error_log($location_description);
    }

    $fallback = 'fallback' . (php_sapi_name() == 'cli' ? '-cli' : null);
    $layout_file = search_plugins('src/php/error/' . $layout . '.php') ?? search_plugins("src/php/error/$fallback.php");

    while (ob_get_level()) {
        ob_end_clean();
    }

    if (file_exists($layout_file)) {
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

function search_plugins($file)
{
    $found = null;

    with_plugins(function($dir, $name) use ($file, &$found) {
        $_filepath = $dir . '/' . $file;

        if (file_exists($_filepath)) {
            $found = $_filepath;
            return true;
        }
    });

    return $found;
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
