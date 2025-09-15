<?php

if (php_sapi_name() !== 'cli') {
    echo '<pre>';
}

echo "\n<<<< $location\n\n";

foreach ($arguments as $i => $argument) {
    if ($i) {
        echo "\n----\n\n";
    }

    var_dump($argument);
}

echo "\n>>>>\n\n";