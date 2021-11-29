<?php
require __DIR__ . '/functions.php';
require __DIR__ . '/src/php/class/Config.php';

Config::set(require APP_HOME . '/config.php');

define('PLUGINS', array_unique(array_merge(@$plugins ?: [], @Config::get()->requires ?? [])));

define_autoloader();
load_plugin_libs();
init_plugins();

$latests = [];

foreach (['collect', 'combine'] as $file) {
    if (!file_exists($path = APP_HOME . '/build/' . $file . '.json')) {
        continue;
    }

    $types = json_decode(file_get_contents($path));

    foreach ($types as $type => $props) {
        if (!@$props->into) {
            continue;
        }

        $into = APP_HOME . '/' . $props->into;

        shell_exec("rm -rf \"{$into}\"");
    }
}

$types = json_decode(file_get_contents(APP_HOME . '/build/collect.json'));

foreach ($types as $type => $props) {
    $latest = 0;
    $schedule = [];

    with_plugins(function($pdir, $name) use ($props, &$schedule, &$latest) {
        $dir = "{$pdir}/" . $props->directory;

        if (!is_dir($dir)) {
            return;
        }

        $handle = opendir($dir);

        while ($file = readdir($handle)) {
            if (preg_match('/^\./', $file)) {
                continue;
            }

            $filepath = $dir . '/' . $file;

            $latest = max(filemtime($filepath), $latest);

            $schedule[] = $filepath;
        }

        closedir($handle);
    });

    $into = APP_HOME . '/' . $props->into;

    @mkdir($into);

    foreach ($schedule as $filepath) {
        if (preg_match('/(.*)(\..*)$/', basename($filepath), $groups)) {
            $filename = $groups[1];
            $ext = $groups[2];
        } else {
            $filename = baename($filepath);
            $ext = '';
        }

        $dest = $into . '/' . $filename . '.' . $latest . $ext;
        @mkdir(dirname($dest), 0777, true);

        shell_exec("cp '{$filepath}' '{$dest}'");
    }

    $latests[$type] = $latest;
}

$types = json_decode(file_get_contents(APP_HOME . '/build/combine.json'));

foreach ($types as $type => $props) {
    $filedata = "";
    $latest = 0;
    $wrapper_open = null;
    $wrapper_close = null;

    if (!@$props->into) {
        echo "skipping {$type} (no into defined)\n";
        continue;
    }

    if (!@$props->basename) {
        echo "skipping {$type} (no basename defined)\n";
        continue;
    }

    if (!@$props->extension) {
        echo "skipping {$type} (no extension defined)\n";
        continue;
    }

    if (@$props->wrapper->open) {
        $wrapper_open = search_plugins($props->wrapper->open);
    }

    if (@$props->wrapper->close) {
        $wrapper_close = search_plugins($props->wrapper->close);
    }

    foreach ($props->files as $file) {
        $filepath = search_plugins(preg_replace('/.*:/', '', $file));

        if (!file_exists($filepath)) {
            echo "skipping {$type} (file {$file} does not exist)\n";
            continue 2;
        }

        ob_start();

        @include $wrapper_open;

        if (preg_match('/^php:.*/', $file)) {
            require $filepath;
        } else {
            readfile($filepath);
        }

        @include $wrapper_close;

        $dfiledata = ob_get_contents();
        $filedata .= $dfiledata;

        ob_end_clean();

        $latest = max(filemtime($filepath), $latest);
    }

    $into = APP_HOME . '/' . $props->into;

    $dest = $into . '/' . $props->basename . '.' . $latest . '.' . $props->extension;
    @mkdir(dirname($dest), 0777, true);
    file_put_contents($dest, $filedata);

    $latests[$type] = $latest;
}

file_put_contents(APP_HOME . '/build/latest.json', json_encode($latests));
