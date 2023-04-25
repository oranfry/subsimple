<?php

use subsimple\Config;

if (!defined('APP_HOME')) {
    error_log('Please define APP_HOME');
    die();
}

require __DIR__ . '/functions.php';

Config::set(require APP_HOME . '/config.php');

define('PLUGINS', array_unique(array_merge(Config::get()->requires ?? [], [__DIR__])));

load_plugin_libs();
init_plugins();
route();

$viewdata = do_controller();

if (!defined('LAYOUT')) {
    define('LAYOUT', 'main');
}

do_layout($viewdata);

deinit_plugins();
