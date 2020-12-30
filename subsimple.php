<?php
if (!defined('APP_HOME')) {
    error_log('Please define APP_HOME');
    die();
}

require __DIR__ . '/functions.php';

define_autoloader();

(function(){
    $config = require APP_HOME . '/config.php';

    Config::set($config);
})();


load_plugin_libs();
init_plugins();
route();
$viewdata = do_controller();

if (!defined('LAYOUT')) {
    define('LAYOUT', 'main');
}

do_layout($viewdata);
