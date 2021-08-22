<?php

require_once __DIR__ . "/../configProcessing.inc.php";
require_once __DIR__ . "/../websocket/client_internal.php";

const MAX_EXECUTION_TIME_S = 60;
$client = null;

initStream();
if (!connectToSocket()) {
    outputSocketError();
    die;
}
sendStillAlive();
if (isset($_GET["getState"])) {
    SocketConnection::requestCurrentState();
    die("OK");
}
readData();


function connectToSocket(): bool {
    global $CONFIG;
    global $client;
    $address = '127.0.0.1';
    $port    = $CONFIG->PORT_WEBSOCKET_SERVER;

    $client = new SocketClient($address, $port);
    return $client->connect();
}

function outputSocketError(): void {
    $errorcode = socket_last_error();
    $errormsg  = socket_strerror($errorcode);
    if ($errorcode == 111) {
        $errormsg = "Connection refused. Please make sure that the socket server is running.";
    }
    sendData('{"action":"error","data":"EError ' . $errorcode . ': ' . $errormsg . '"}', 100000000);
}

function sendStillAlive(): void {
    sendData('{"action":"status","data":"9Connected"}');
}


function readData(): void {
    global $client;
    $timeStart = microtime(true);
    while (microtime(true) - $timeStart < MAX_EXECUTION_TIME_S) {
        $data = $client->readData();
        if ($data !== false && $data != "")
            sendData($data);
        else {
            if (socket_last_error() != 0) {
                if (socket_last_error() != 11)
                    outputSocketError();
                die();
            }
            sendStillAlive();
        }

    }
    $client->close();
}

function sendData(string $data, int $retryMs = 10): void {
    echo "retry: {$retryMs}\n";
    echo "data: {$data}\n\n";
    flush();
}

function initStream(): void {
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
