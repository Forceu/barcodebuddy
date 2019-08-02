<?php
require __DIR__ . '/php-websocket/src/Client.php';


function sendWSResult($resultValue, $name) {
global $BBCONFIG;
$client = new \Bloatless\WebSocket\Client;
if ($client->connect('127.0.0.1', $BBCONFIG["WS_PORT"], '/screen')) {

$payload = json_encode([
    'action' => 'echo',
    'data' => $resultValue.$name,
]);
$client->sendData($payload);
}
}
?>
