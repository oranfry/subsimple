<?php

require search_plugins('src/php/error/_common/prepare.php');

// Show code

if (php_sapi_name() !== 'cli') {
    ?><h1><?= $code ?></h1><?php
}

// Show the public exception class

if ($public_exception) {
    if (php_sapi_name() !== 'cli') {
        ?><h2><?php
    }

    echo $public_exception;

    if (php_sapi_name() === 'cli') {
        echo "\n";
    } else {
        ?></h2><?php
    }
}

// Show public message

if ($public_message) {
    if (php_sapi_name() !== 'cli') {
        ?><pre style="font-size: 1.4em"><?php
    }

    $value = is_string($public_message) ? $public_message : var_export($public_message, true);

    if (php_sapi_name() !== 'cli') {
        $value = htmlspecialchars($value);
    }

    echo $value;

    if (php_sapi_name() === 'cli') {
        echo "\n";
    } else {
        ?></pre><?php
    }
}

// Showing details as appropriate

if (defined('SHOW_ERRORS') && SHOW_ERRORS) {
    // Show a boundary between public and private details

    if (php_sapi_name() === 'cli') {
        echo "--------------------\n";
    } else {
        ?><div style="border-top: 1px solid #999; margin: 2em 0"></div><?php
    }

    // Styles for HTML

    if (php_sapi_name() !== 'cli') {
        ?><style>
            .backtrace-line {
                border: 1px solid #977;
                background-color: #fee;
                padding: 1em;
                white-space: pre;
                font-family: monospace;
                overflow: hidden;
            }

            .backtrace-line + .backtrace-line {
                margin-top: 1em;
            }
        </style><?php
    }

    // Show the Exception class

    if (php_sapi_name() !== 'cli') {
        ?><h2><?php
    }

    echo get_class($exception);

    if (php_sapi_name() === 'cli') {
        echo "\n";
    } else {
        ?></h2><?php
    }

    // Show the Exception message

    if ($exception->getMessage()) {
        if (php_sapi_name() !== 'cli') {
            ?><pre style="font-size: 1.4em"><?php
        }

        $value = $exception->getMessage();

        if (php_sapi_name() !== 'cli') {
            $value = htmlspecialchars($value);
        }

        echo $value;

        if (php_sapi_name() === 'cli') {
            echo "\n";
        } else {
            ?></pre><br><?php
        }
    }

    // Show the backtrace

    foreach ($exception->getTrace() as $i => $bt) {
        if (php_sapi_name() !== 'cli') {
            ?><div class="backtrace-line" style="max-height: 5em" onclick="this.style.maxHeight = '';"><?php
        }

        if (@$bt['file']) {
            echo $bt['file'] . ':' . $bt['line'] . "\n";
        }

        if (@$bt['function']) {
            echo "    " . $bt['function'];

            if (isset($bt['args'])) {
                echo "(";

                foreach ($bt['args'] as $i => $arg) {
                    echo($i ? ', ' : '');

                    $value = var_export($arg, 1);

                    if (php_sapi_name() !== 'cli') {
                        $value = htmlspecialchars($value);
                    }

                    echo $value;
                }

                echo ")\n";
            }
        }

        if (php_sapi_name() !== 'cli') {
            echo '</div>';
        }
    }
}

if (php_sapi_name() === 'cli') {
    exit($code);
}
