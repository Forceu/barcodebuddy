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

function sendWebsocketMessage($resultText, $websocketEnabled, $resultCode = "0") {
global $BBCONFIG;
    if ($BBCONFIG["WS_USE"] && $websocketEnabled) {
        require_once __DIR__ . "/websocket/client_internal.php";
        sendWSResult($resultCode, $resultText);
    }
}

?>
