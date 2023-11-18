<?php

require search_plugins('src/php/error/_common/prepare.php');

$public = array_filter([
    'exception' => $public_exception,
    'message' => is_string($public_message) || is_null($public_message) ? $public_message : var_export($public_message, true),
]);

$private = [];

if (defined('SHOW_ERRORS') && SHOW_ERRORS) {
    $private = [
        'private_exception' => get_class($exception),
        'private_message' => $exception->getMessage(),
        'trace' => $exception->getTrace(),
    ];
}

if (php_sapi_name() != 'cli' && !headers_sent()) {
    header('Content-Type: application/json');
}

echo json_encode($public + $private, JSON_UNESCAPED_SLASHES);
