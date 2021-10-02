<?php
if (!defined('APP_HOME')) {
    error_log('Please define APP_HOME');
    die();
}

require __DIR__ . '/functions.php';
require __DIR__ . '/src/php/class/Config.php';

Config::set(require APP_HOME . '/config.php');

define('PLUGINS', array_unique(array_merge(@$plugins ?: [], @Config::get()->requires ?? [])));
define_autoloader();
load_plugin_libs();
init_plugins();
route();

$viewdata = do_controller();


if (!defined('LAYOUT')) {
    define('LAYOUT', 'main');
}

do_layout($viewdata);

deinit_plugins();
