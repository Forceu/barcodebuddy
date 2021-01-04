<?php
/**
 * Barcode Buddy for Grocy
 *
 * PHP version 7
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */


/**
 * Functions for sending a websocket message to WS server
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.2
 *
 */

require_once __DIR__ . '/../configProcessing.inc.php';
require_once __DIR__ . '/../websocketconnection.inc.php';
require_once __DIR__ . '/../processing.inc.php';

//Send result of a barcode entry
function sendWSResult($resultValue, $name) {
    global $CONFIG;
    $client = getClient();
    if ($client->connect('127.0.0.1', $CONFIG->PORT_WEBSOCKET_SERVER, '/screen')) {
        $payload = json_encode(array(
            'action' => 'echo',
            'data'   => $resultValue . $name
        ));
        $client->sendData($payload);
    }
}

function requestSavedState() {
    global $CONFIG;
    $client = getClient();
    if ($client->connect('127.0.0.1', $CONFIG->PORT_WEBSOCKET_SERVER, '/screen')) {
        $payload = json_encode(array(
            'action' => 'getmode',
            'data'   => ''
        ));
        $client->sendData($payload);
    }
}

//Send current Barcode Buddy state
function sendNewState($newState) {
    global $CONFIG;
    $client = getClient();
    if ($client->connect('127.0.0.1', $CONFIG->PORT_WEBSOCKET_SERVER, '/screen')) {
        $payload = json_encode(array(
            'action' => 'setmode',
            'data'   => stateToString($newState)
        ));
        $client->sendData($payload);
    }
}

function getClient() {
    require_once __DIR__ . '/php-websocket/src/Client.php';
    return new \Bloatless\WebSocket\Client;
}