<?php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/websocket_client.php";
require_once __DIR__ . "/../websocketconnection.inc.php";

const MAX_EXECUTION_TIME_S = 60;

initStream();
connectToWebsocket();
sendStillAlive();
if (isset($_GET["getState"])) {
    requestCurrentState();
    die("OK");
}
readData();


function connectToWebsocket() {
    global $sp;
    global $CONFIG;
    if (!($sp = websocket_open('localhost', $CONFIG->PORT_WEBSOCKET_SERVER, '', $errorstr, 15))) {
        if (strpos($errorstr, "Connection refused") !== false)
            sendData('{"action":"error","data":"EConnection to websocket server refused! Please make sure that it has been started."}', "100000000");
        else
            sendData('{"action":"error","data":"E' . $errorstr . '"}', "100000000");
        die();
    }
}

function sendStillAlive() {
    sendData('{"action":"status","data":"9Connected"}');
}


function readData() {
    global $sp;
    
    $timeStart = microtime(true);
    while (microtime(true) - $timeStart < MAX_EXECUTION_TIME_S) {
        $data = websocket_read($sp, $errstr);
        if ($data != "")
            sendData($data);
        else
            sendStillAlive();
       }
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