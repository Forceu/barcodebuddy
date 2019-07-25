<?php
require __DIR__ . '/php-websocket/src/Client.php';


function sendWSResult($resultValue, $name) {
$client = new \Bloatless\WebSocket\Client;
if ($client->connect('127.0.0.1', WEBSOCKET_SERVER_PORT, '/screen')) {

$payload = json_encode([
    'action' => 'echo',
    'data' => $resultValue.$name,
]);
$client->sendData($payload);
}
}
?>
