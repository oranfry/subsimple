<?php

echo "\n<<<< $location\n\n";

foreach ($arguments as $i => $argument) {
    if ($i) {
        echo "\n----\n\n";
    }

    var_dump($argument);
}

echo "\n>>>>\n\n";