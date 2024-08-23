<?php

if (php_sapi_name() !== 'cli') {
    echo '<pre>';
}

if (php_sapi_name() === 'cli') {
    $wrap = 'identity';
} else {
    $wrap = 'htmlspecialchars';
}

foreach ($arguments as $i => $argument) {
    if ($i) {
        echo "\n\n----------\n\n";
    }

    echo $wrap(json_encode($argument, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
