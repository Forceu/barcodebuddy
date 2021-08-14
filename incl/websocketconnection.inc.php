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
 * Helper for websocket
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 *
 */

require_once __DIR__ . "/internalChecking.inc.php";
require_once __DIR__ . "/websocket/client_internal.php";

class SocketConnection {

    /**
     * Requests the current consumption state
     */
    public static function requestCurrentState() {
        $client = SocketConnection::getClient();
        if ($client->connect()) {
            $client->sendData('0 ');
            $client->close();
        }
    }

    /**
     * Send current Barcode Buddy state
     * @param int $newState Identifier of BB state
     */
    public static function sendWebsocketStateChange(int $newState) {
        $client = SocketConnection::getClient();
        if ($client->connect()) {
            $client->sendData('1' . stateToString($newState));
            $client->close();
        }
    }

    /**Send result of a barcode entry
     * @param int $resultValue
     * @param string $text
     */
    public static function sendWebsocketMessage(int $resultValue, string $text) {
        $client = SocketConnection::getClient();
        if ($client->connect()) {
            $client->sendData('2' . $resultValue . $text);
            $client->close();
        }
    }

    private static function getClient(): SocketClient {
        global $CONFIG;
        return new SocketClient('127.0.0.1', $CONFIG->PORT_WEBSOCKET_SERVER);
    }
}
