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
 * This file starts the websocket server and needs to be called from the command line
 * to work
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */


require_once __DIR__ . '/incl/configProcessing.inc.php';
require_once __DIR__ . '/incl/websocketconnection.inc.php';
require_once __DIR__ . '/incl/internalChecking.inc.php';


if (checkExtensionsInstalled()["result"] != RESULT_ALL_INSTALLED) {
    die("Not all required extensions are installed. Please run setup.php for more information.");
}

$address = '127.0.0.1';
$port    = $CONFIG->PORT_WEBSOCKET_SERVER;

echo "Starting socket server on $address:$port\n";

set_time_limit(0);
ob_implicit_flush();

// current mode is stored
$currentBBMode = "Consume";
//Only these modes are allowed as input
$allowedModes = array("Consume", "Consume (spoiled)", "Purchase", "Open", "Inventory", "Quantity", "Add to shoppinglist");
//null var
$null = null;


$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($socket === false) {
    showErrorAndDie("socket_create()");
}

if (socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) === false) {
    showErrorAndDie("socket_set_option()");
}

if (socket_bind($socket, $address, $port) === false) {
    showErrorAndDie("socket_bind()");
}

if (socket_listen($socket) === false) {
    showErrorAndDie("socket_listen()");
}

$clients = array($socket);

while (true) {
    //manage multiple connections
    $changed = $clients;
    //returns the socket resources in $changed array
    socket_select($changed, $null, $null, $null, 0);

    //check for new clients
    if (in_array($socket, $changed)) {
        $socket_new   = socket_accept($socket); //accept new socket
        $clients[]    = $socket_new; //add socket to client array
        $found_socket = array_search($socket, $changed);
        unset($changed[$found_socket]);
    }

    //loop through all connected clients
    foreach ($changed as $changed_socket) {
        $buf = @socket_read($changed_socket, 1024);
        if ($buf === false || $buf === "") { // check disconnected client
            // remove client for $clients array
            $found_socket = array_search($changed_socket, $clients);
            unset($clients[$found_socket]);
        } else {
            // A message was received
            $command = $buf[0];
            $data    = substr($buf, 1);
            switch ($command) {
                // Get mode
                case '0':
                    sendMode();
                    break;
                // Set mode
                case '1':
                    if (in_array($data, $allowedModes)) {
                        $currentBBMode = $data;
                    }
                    sendMode();
                    break;
                // Echo
                case '2':
                    sendMessage('{"action":"echo","data":"' . $data . '"}');
                    break;
                // Invalid command
                default:
                    echo "Unknown command " . $buf;
            }
        }
    }
}

function sendMode(): void {
    global $currentBBMode;
    sendMessage('{"action":"getmode","data":"4' . $currentBBMode . '"}');
}

function sendMessage(string $msg): void {
    global $clients;
    foreach ($clients as $changed_socket) {
        @socket_write($changed_socket, $msg, strlen($msg));
    }
}

/**
 * @return never
 */
function showErrorAndDie(string $functionName): void {
    echo $functionName . " failed. Reason: " . socket_strerror(socket_last_error()) . "\n";
    sleep(5);
    die();
}
