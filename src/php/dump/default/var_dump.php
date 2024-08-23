<?php

if (php_sapi_name() !== 'cli') {
    echo '<pre>';
}

foreach ($arguments as $i => $data) {
    if ($i) {
        echo "\n----------\n\n";
    }

    var_dump($data);
}
