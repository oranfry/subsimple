<?php

echo "\n\n<<<< $location\n\n";

foreach ($arguments as $i => $argument) {
    if ($i) {
        echo "\n\n----\n\n";
    }

    echo json_encode($argument, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

echo "\n\n>>>>\n\n";