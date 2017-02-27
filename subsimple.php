<?php

function findBy($by, $value, $items)
{
    foreach ($items as $item) {
        $item = (object) $item;

        if (!property_exists($item, $by)) {
            $call = debug_backtrace()[1];

            error_log('Cannot find by ' . $by . ' in ' . $call['file'] . ' line ' . $call['line']);
            error_response('Internal Server Error', 500);
        }

        if ($item->{$by} == $value) {
            return $item;
        }
    }
}

function findManyBy($by, $value, $items)
{
    $results = [];

    foreach ($items as $item) {
        $item = (object) $item;
        if ($item->{$by} == $value) {
            $results[] = $item;
        }
    }

    return $results;
}


function findById($id, $items)
{
    return findBy('id', $id, $items);
}

function findByObjCode($objCode, $items)
{
    return findBy('objCode', $objCode, $items);
}

function findByItemCode($itemCode, $items)
{
    return findBy('itemCode', $itemCode, $items);
}

function findByName($name, $items)
{
    return findBy('name', $name, $items);
}

function findByNumber($number, $items)
{
    return findBy('number', $number, $items);
}

function findByCode($code, $items)
{
    return findBy('code', $code, $items);
}

function findManyByObjCode($objCode, $items) {
    return findManyBy('objCode', $objCode, $items);
}

function findManyByItemCode($itemCode, $items) {
    return findManyBy('itemCode', $itemCode, $items);
}

function pad($items, $padding, $by)
{
    $results = [];

    foreach ($items as $item) {
        $matchingItem = findBy($by, $item->{$by}, $padding);

        $results[] = (object) array_merge((array) $item, (array) $matchingItem);
    }

    return $results;
}

function var_stream() {
    $file = 'public/errors.html';
    if (!file_exists($file)) {
        var_stream_init();
    }
    ob_start();
    call_user_func_array('_var_die', array_merge([1], func_get_args()));
    file_put_contents($file, ob_get_clean(), FILE_APPEND);
}

function var_stream_init() {
    $file = 'public/errors.html';
    $contents = <<<EOF
<html>
    <body style="margin-top: 65px">
        <div style="padding: 10px; margin-bottom: 10px; position: fixed; top: 0; left: 0; right: 0; background-color: #ccc;">
            <a href="/init_stream.php">reinit</a>
        </div>
EOF;
    file_put_contents($file, $contents);
}


function var_die_xml($xml) {
    if (!headers_sent()) {
        header("Content-Type: application/xml");
    }
    echo $xml;
    die();
}

function var_stream_xml($xml)
{
    $orig_max_data = ini_get('xdebug.var_display_max_data');
    ini_set('xdebug.var_display_max_data', 102400);
    var_stream(htmlspecialchars(toPrettyXml($xml)));
    ini_set('xdebug.var_display_max_data', $orig_max_data);
}

function var_die() {
    if (!headers_sent()) {
        header("Content-Type: text/html");
    }
    call_user_func_array('_var_die', array_merge([1], func_get_args()));
    die("<hr><p style='font-family: monospace; text-align: center'>var_die</p>");
}

function _var_die() {
    $args = func_get_args();
    $caller = debug_backtrace()[array_shift($args) + 1];
    print("<div style='margin-bottom: 40px'>");
    print("<div style='margin-bottom: 20px'><span style='margin: 0 0 15px; padding: 10px; background-color: #d7bcec; font-family: monospace'><strong>" . @$caller['file'] . "</strong> line <strong>" . @$caller['line'] . "</strong></span></div>");
    print("<pre>");
    if (count($args)) {
        call_user_func_array('var_dump', $args);
    }
    print("</pre>");
    print("</div>");

    // trigger_error("DEBUG", E_USER_ERROR);
}

function toPrettyXml($xml) {
    $simplexml = is_string($xml) ? simplexml_load_string($xml) : $xml;
    $dom = dom_import_simplexml($simplexml)->ownerDocument;
    $dom->formatOutput = true;

    return $dom->saveXML();
}

function error_response($message, $statusCode = "400", $statusText = null)
{
    $texts = [
        "400" => "Bad Request",
        "404" => "Not Found",
        "500" => "Internal Server Error",
    ];

    if (!$statusText) {
        $statusText = @$texts[$statusCode] ?: "Oh Dear";
    }

    header("HTTP/1.1 $statusCode $statusText");

    die(json_encode(['error' => $message]));
}

function parse_json_request($paramNames) 
{
    $data = json_decode(file_get_contents('php://input'), true);

    if ($data === null) {
        error_response('Request body was not valid JSON', 400);
    }

    sort($paramNames);

    $providedKeys = array_keys($data);
    sort($providedKeys);

    if ($paramNames != $providedKeys) {
        error_response('Invalid request params', 400);
    }

    return $data;
}
