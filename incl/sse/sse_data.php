<?php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/websocket_client.php";
require_once __DIR__ . "/../websocketconnection.inc.php";

initStream();
connectToWebsocket();
if (!isset($_GET["onlyrefresh"]))
	requestData();
readData();


function connectToWebsocket() {
    global $sp;
    if (!($sp = websocket_open('localhost', PORT_WEBSOCKET_SERVER, '', $errorstr, 30))) {
        if (strpos($errorstr, "Connection refused") !== false)
            sendData('{"action":"error","data":"EConnection to websocket server refused! Please make sure that it has been started."}', "100000000");
        else
            sendData('{"action":"error","data":"E' . $errorstr . '"}', "100000000");
        die();
    }
}

function requestData() {
    global $sp;
    requestCurrentState();
    readData();
}

//Originally this was in a while loop, however for some reason it caused php-fpm to lock up
function readData() {
    global $sp;
    
    $data = websocket_read($sp, $errstr);
    if ($data != "")
        sendData($data);
}

function sendData($data, $retryMs = 10) {
    echo "retry: {$retryMs}\n";
    echo "data: {$data}\n\n";
    flush();
}

function initStream() {
    set_time_limit(85);
    @ini_set('auto_detect_line_endings', 1);
    @ini_set('max_execution_time', 85);
    
    @ini_set('zlib.output_compression', 0);
    @ini_set('implicit_flush', 1);
    @ob_end_clean();
    
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Expose-Headers: X-Events');
}

?>