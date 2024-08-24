<?php

echo "\n\n[\n\n";

foreach ($arguments as $i => $argument) {
    if ($i) {
        echo "\n\n" . str_repeat('-', 20) . "\n\n";
    }

    echo json_encode($argument, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

echo "\n\n]\n\n";