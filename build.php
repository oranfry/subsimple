<?php
require __DIR__ . '/functions.php';
define_autoloader();

(function(){
    $config = require APP_HOME . '/config.php';

    Config::set($config);
})();

load_plugin_libs();
init_plugins();

$latests = [];

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

    shell_exec("rm -rf \"{$into}\"");
    mkdir($into);

    foreach ($schedule as $filepath) {
        if (preg_match('/(.*)(\..*)$/', basename($filepath), $groups)) {
            $filename = $groups[1];
            $ext = $groups[2];
        } else {
            $filename = baename($filepath);
            $ext = '';
        }

        $dest = $into . '/' . $filename . '.' . $latest . $ext;

        shell_exec("cp '{$filepath}' '{$dest}'");
    }

    $latests[$type] = $latest;
}

$types = json_decode(file_get_contents(APP_HOME . '/build/combine.json'));

foreach ($types as $type => $props) {
    $filedata = "";
    $latest = 0;

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

    foreach ($props->files as $file) {
        $filepath = null;

        with_plugins(function($pdir, $name) use ($file, &$filepath) {
            $_file_path = "{$pdir}/" . preg_replace('/.*:/', '', $file);

            if (!file_exists($_file_path) || is_dir($_file_path)) {
                return;
            }

            $filepath = $_file_path;

            return true;
        });

        if (!file_exists($filepath)) {
            echo "skipping {$type} (file {$file} does not exist)\n";
            continue 2;
        }

        if (preg_match('/^php:(.*)/', $file, $groups)) {
            ob_start();

            require $filepath;

            $dfiledata = ob_get_contents();
            $filedata .= $dfiledata;

            ob_end_clean();
        } else {
            $filedata .= file_get_contents($filepath);
        }

        $latest = max(filemtime($filepath), $latest);
    }

    $into = APP_HOME . '/' . $props->into;

    shell_exec("rm -rf \"{$into}\"");
    mkdir($into);
    file_put_contents($into . '/' . $props->basename . '.' . $latest . '.' . $props->extension, $filedata);

    $latests[$type] = $latest;
}

file_put_contents(APP_HOME . '/build/latest.json', json_encode($latests));
