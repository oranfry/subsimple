<?php

echo "Error ($code)\n";
echo is_string($error) ? $error : var_export($error, true) . "\n";

if (defined('SHOW_ERRORS') && SHOW_ERRORS) {
    echo "\n";

    foreach (debug_backtrace() as $i => $bt) {
        if (@$bt['file']) {
            echo $bt['file'] . ':' . $bt['line'] . "\n";
        }
    }

    echo "\n";
}

echo "\n";
