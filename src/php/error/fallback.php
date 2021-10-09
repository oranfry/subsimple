<style>
    .backtrace-line {
        border: 1px solid #977;
        background-color: #fee;
        padding: 1em;
        white-space: pre;
        font-family: monospace;
    }

    .backtrace-line + .backtrace-line {
        margin-top: 1em;
    }
</style>

<h1><?= $code ?></h1>
<pre style="font-size: 1.4em"><?= htmlspecialchars(is_string($error) ? $error : var_export($error, true)); ?></pre>

<?php

if (!defined('SHOW_ERRORS') || !SHOW_ERRORS) {
    return;
}

foreach (debug_backtrace() as $i => $bt) {
    echo '<div class="backtrace-line" style="max-height: 5em; overflow: hidden" onclick="this.style.maxHeight = \'\';">';

    if (@$bt['file']) {
        echo $bt['file'] . ':' . $bt['line'] . "\n";
    }

    if (@$bt['function']) {
        echo "    " . $bt['function'];

        if (isset($bt['args'])) {
            echo "(";

            foreach ($bt['args'] as $i => $arg) {
                echo($i ? ', ' : '') . htmlspecialchars(var_export($arg, 1));
            }

            echo ")";
        }
    }
    echo '</div>';
}
