<?php
function with_plugins($callback)
{
    // "base" plugin
    if ($callback(APP_HOME, null)) {
        return true;
    }

    $plugins_dir = APP_HOME . '/plugins';
    $h = opendir($plugins_dir);
    $result = false;

    while ($d = readdir($h)) {
        if (preg_match('/^\./', $d)) {
            continue;
        }

        $plugin_dir = $plugins_dir . '/' . $d;

        if (!is_dir($plugin_dir)) {
            continue;
        }

        if ($callback($plugin_dir, $d)) {
            $result = true;
            break;
        }
    }

    closedir($h);

    if (defined('CUSTOM_PLUGINS')) {
        foreach (CUSTOM_PLUGINS as $plugin_dir) {
            if ($callback($plugin_dir, basename($plugin_dir))) {
                $result = true;
                break;
            }
        }
    }

    return $result;
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
        $init_func = 'init_' . $name;

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