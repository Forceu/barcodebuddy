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
require_once __DIR__ . '/../processing.inc.php';


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
            return false;
        }
        if (@socket_connect($this->socket, $this->address, $this->port) === false) {
            return false;
        }
        return true;
    }

    public function sendData(string $data): void {
        socket_write($this->socket, $data, strlen($data));
    }

    /**
     * @return false|string
     */
    public function readData() {
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec" => 60, "usec" => 0));
        return socket_read($this->socket, 2048);
    }

    public function close(): void {
        socket_close($this->socket);
    }


    public function getLastError(): string {
        return socket_strerror(socket_last_error());
    }

}