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

function sendWebsocketMessage($resultText, $websocketEnabled, $resultCode = "0") {
global $BBCONFIG;
    if ($BBCONFIG["WS_USE"] && $websocketEnabled && isExtensionInstalled("sockets")) {
        require_once __DIR__ . "/websocket/client_internal.php";
        sendWSResult($resultCode, $resultText);
    }
}


function isNewServerSupported() {
    // PHP_VERSION_ID is available as of PHP 5.2.7, if our 
    // version is lower than that, then emulate it
    if (!defined('PHP_VERSION_ID')) {
        $version = explode('.', PHP_VERSION);
        define('PHP_VERSION_ID', ($version[0] * 10000 + $version[1] * 100 + $version[2]));
    }
    if (PHP_VERSION_ID < 50207) {
        define('PHP_MAJOR_VERSION', $version[0]);
        define('PHP_MINOR_VERSION', $version[1]);
        define('PHP_RELEASE_VERSION', $version[2]);
    }
    
    return (PHP_VERSION_ID >= 70200);
}

?>
