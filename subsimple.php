<?php

use subsimple\Config;

if (!defined('APP_HOME')) {
    error_log('Please define APP_HOME');
    die();
}

require __DIR__ . '/functions.php';

Config::set(require APP_HOME . '/subsimple-config.php');

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
    (function () use ($exception) {
        $layout_paths = array_filter([
            defined('LAYOUT') ? LAYOUT : null,
            'default',
        ]);

        for (
            $class = get_class($exception);
            $class !== false;
            $class = get_parent_class($class)
        ) {
            foreach ($layout_paths as $error_layout) {
                $include = 'src/php/error/' . $error_layout . '/' . str_replace('\\', '/', $class) . '.php';

                foreach (search_plugins_all($include) as $handler) {
                    if (require $handler ?? true) {
                        return;
                    }
                }
            }
        }
    })();
}
