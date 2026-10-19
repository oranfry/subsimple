<?php

use OranFry\Subsimple\Builder;
use OranFry\Subsimple\Config;

require __DIR__ . '/functions.php';

Config::set(require APP_HOME . '/subsimple-config.php');

define('PLUGINS', array_unique(array_merge(Config::get()->requires ?? [], [__DIR__])));

load_plugin_libs();
init_plugins();

Builder::build();
