<?php


require_once __DIR__ . '/../websocketconnection.inc.php';
require_once __DIR__ . '/../processing.inc.php';

function sendWSResult($resultValue, $name) {
    global $BBCONFIG;
    if (isNewServerSupported()) {
        require_once __DIR__ . '/php-websocket/src/Client.php';
        $client = new \Bloatless\WebSocket\Client;
    } else {
        require_once __DIR__ . '/php-websocket-1.0/class.websocket_client.php';
        $client = new WebsocketClient;
    }
        if ($client->connect('127.0.0.1', $BBCONFIG["WS_PORT"], '/screen')) {
            $payload = json_encode(array(
                'action' => 'echo',
                'data' => $resultValue . $name
            ));
            $client->sendData($payload);
        }
}

function sendNewState($newState) {
   global $BBCONFIG;
    if (isNewServerSupported()) {
        require_once __DIR__ . '/php-websocket/src/Client.php';
        $client = new \Bloatless\WebSocket\Client;
    } else {
        require_once __DIR__ . '/php-websocket-1.0/class.websocket_client.php';
        $client = new WebsocketClient;
    }
        if ($client->connect('127.0.0.1', $BBCONFIG["WS_PORT"], '/screen')) {
            $payload = json_encode(array(
                'action' => 'setmode',
                'data' => stateToString($newState)
            ));
            $client->sendData($payload);
        }
}
?>
