<?php

echo "\n[\n\n";

foreach ($arguments as $i => $argument) {
    if ($i) {
        echo "\n" . str_repeat('-', 20) . "\n\n";
    }

    var_dump($argument);
}

echo "\n]\n\n";