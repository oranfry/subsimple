<?php

$code = (int)($code ?? (php_sapi_name() == 'cli' ? 1 : 500));
$private_message = $exception->getMessage();
$public_exception ??= null;
$public_message ??= null;

if (php_sapi_name() != 'cli' && !headers_sent()) {
    http_response_code($code);
}

// Send to log as appropriate

if (!($suppress_log ?? false)) {
    error_log(implode(' ', array_filter([
        $code,
        $public_exception,
        $public_message,
        '--',
        get_class($exception),
        $private_message,
    ])));

    foreach ($exception->getTrace() as $trace) {
        $location_description = implode(':', array_filter([@$trace['file'], @$trace['line']]));

        if (@$trace['function']) {
            $location_description .= ($location_description ? ' ' : null) .  '(' . $trace['function'] . ')';
        }

        error_log($location_description);
    }
}
