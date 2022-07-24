<?php

use subsimple\Config;

require __DIR__ . '/functions.php';

Config::set(require APP_HOME . '/config.php');

define('PLUGINS', array_unique(array_merge(@$plugins ?: [], @Config::get()->requires ?? [])));

load_plugin_libs();
init_plugins();

$latests = [];

if (!file_exists($path = APP_HOME . '/build.json')) {
    error_log('Buildfile does not exist, looking for: ' . $path . "\n");
    exit(1);
}

$build = json_decode(file_get_contents($path));

if (!@$build->into) {
    error_log('Please specify "into" in buildfile');
    exit(1);
}

$build_into = APP_HOME . '/' . $build->into;

shell_exec("rm -rf \"{$build_into}\"");

foreach (@$build->collect ?? [] as $type => $props) {
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

    $into = $build_into . '/' . $props->into;

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

foreach (@$build->combine ?? [] as $type => $props) {
    $filedatas = [];
    $wrapper_close = null;
    $wrapper_open = null;
    $separator = null;

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

    if (@$props->wrapper->separator) {
        if ($wrapper_separator = search_plugins($props->wrapper->separator)) {
            ob_start();

            require $wrapper_separator;

            $separator = ob_get_contents();

            ob_end_clean();
        } else {
            echo 'wrapper separator missing [' . $props->wrapper->separator . ']' . "\n";
        }
    }

    if (@$props->wrapper->open) {
        if (!$wrapper_open = search_plugins($props->wrapper->open)) {
            echo 'wrapper open missing' . "\n";
        }
    }

    if (@$props->wrapper->close) {
        if (!$wrapper_close = search_plugins($props->wrapper->close)) {
            echo 'wrapper close missing' . "\n";
        }
    }

    foreach (array_values($props->files) as $i => $file) {
        $filepath = search_plugins(preg_replace('/.*:/', '', $file));

        if (!file_exists($filepath)) {
            echo "skipping {$type} (file {$file} does not exist)\n";
            continue 2;
        }

        ob_start();

        if ($wrapper_open) {
            require $wrapper_open;
        }

        if (preg_match('/^php:.*/', $file)) {
            require $filepath;
        } else {
            readfile($filepath);
        }

        if ($wrapper_close) {
            require $wrapper_close;
        }

        $filedatas[] = ob_get_contents();

        ob_end_clean();
    }

    $filedata = implode($separator, $filedatas);
    $latest = hash('SHA256', $filedata);
    $into = $build_into . '/' . $props->into;
    $dest = $into . '/' . $props->basename . '.' . $latest . '.' . $props->extension;

    @mkdir(dirname($dest), 0777, true);
    file_put_contents($dest, $filedata);

    foreach ($props->then ?? [] as $command_template) {
        shell_exec(str_replace('{}', $dest, $command_template));
    }

    $latests[$type] = $latest;
}

file_put_contents(APP_HOME . '/latest.json', json_encode($latests));
