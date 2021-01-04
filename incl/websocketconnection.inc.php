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

function sendWebsocketMessage($resultText, $resultCode = "0") {
    require_once __DIR__ . "/websocket/client_internal.php";
    sendWSResult($resultCode, $resultText);
}


function requestCurrentState() {
    require_once __DIR__ . "/websocket/client_internal.php";
    requestSavedState();
}

function sendWebsocketStateChange($newState) {
    require_once __DIR__ . "/websocket/client_internal.php";
    sendNewState($newState);
}

