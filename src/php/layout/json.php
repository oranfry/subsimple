<?php

header('Content-Type: application/json');

if (isset($error)) {
    header('HTTP/1.1 400 Bad Request', true, 400);

    echo json_encode(['error' => $error]);

    return;
}

foreach ($headers ?? [] as $header => $value) {
    header($header . ': ' . $value);
}

if (@$data !== null) {
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
}
