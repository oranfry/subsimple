<?php

use subsimple\Config;

if (!defined('APP_HOME')) {
    error_log('Please define APP_HOME');
    die();
}

require __DIR__ . '/functions.php';

Config::set(require APP_HOME . '/config.php');

define('PLUGINS', array_unique(array_merge(Config::get()->requires ?? [], [__DIR__])));

try {
    load_plugin_libs();
    init_plugins();
    route();

    $viewdata = do_controller();

    if (!defined('LAYOUT')) {
        define('LAYOUT', 'main');
    }

    do_layout($viewdata);

    deinit_plugins();
} catch (Exception $exception) {
    for (
        $class = get_class($exception), $handled = false;
        !$handled && $class !== false;
        $class = get_parent_class($class)
    ) {
        if ($handler_file = search_plugins('src/php/error/' . str_replace('\\', '/', $class) . '.php')) {
            $handled = require $handler_file ?? true;
        }
    }
}