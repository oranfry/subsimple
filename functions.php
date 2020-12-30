<?php
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

function load_plugin_libs()
{
    with_plugins(function($dir, $name) {
        @include $dir . '/src/php/script/lib.php';
    });
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
    if (php_sapi_name() != 'cli') {
        http_response_code($code ?? 400);
    }

    $error = $message;
    $layout = defined('LAYOUT') ? LAYOUT : 'main';

    error_log("{$code} {$message}");
    error_log(var_export(debug_backtrace(), 1));

    foreach ([APP_HOME . '/src/php/layout/' . $layout . '-error.php', APP_HOME . '/src/php/layout/error.php'] as $layout_file) {
        if (file_exists($layout_file)) {
            require $layout_file;
            die();
        }
    }

    echo "An error occurred but we are unable to display any details<br>";

    if (php_sapi_name() == 'cli') {
        die($code ?? 1);
    }

    die();
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
