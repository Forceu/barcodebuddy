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


function requestSavedState() {
    $client = getClient();
    if ($client->connect()) {
        $client->sendData('0 ');
        $client->close();
    }
}

//Send current Barcode Buddy state
function sendNewState($newState) {
    $client = getClient();
    if ($client->connect()) {
        $client->sendData('1' . stateToString($newState));
        $client->close();
    }
}

//Send result of a barcode entry
function sendWSResult($resultValue, $name) {
    $client = getClient();
    if ($client->connect()) {
        $client->sendData('2' . $resultValue . $name);
        $client->close();
    }
}

function getClient(): SocketClient {
    global $CONFIG;
    return new SocketClient('127.0.0.1', $CONFIG->PORT_WEBSOCKET_SERVER);
}

class SocketClient {
    private $address;
    private $port;
    private $socket;

    public function __construct(string $address, int $port) {
        $this->address = $address;
        $this->port    = $port;
    }

    public function connect(): bool {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            echo "socket_create() failed: reason: " . getLastError() . "\n";
            return false;
        }
        if (socket_connect($this->socket, $this->address, $this->port) === false) {
            echo "socket_connect() failed.\nReason: " . socket_strerror(socket_last_error($this->socket)) . "\n";
            return false;
        }
        return true;
    }

    public function sendData(string $data) {
        socket_write($this->socket, $data, strlen($data));
    }

    /**
     * @return false|string
     */
    public function readData() {
        return socket_read($this->socket, 2048);
    }

    public function close() {
        socket_close($this->socket);
    }


    public function getLastError(): string {
        return socket_strerror(socket_last_error());
    }

}