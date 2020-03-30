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
 * @since      File available since Release 1.2
 */


/**
 * General settings
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.2
 *
 */



require_once __DIR__ . "/../incl/config.php";
require_once __DIR__ . "/../incl/api.inc.php";
require_once __DIR__ . "/../incl/db.inc.php";
require_once __DIR__ . "/../incl/processing.inc.php";
require_once __DIR__ . "/../incl/websocketconnection.inc.php";
require_once __DIR__ . "/../incl/webui.inc.php";


//Save settings
if (isset($_POST["isSaved"])) {
        saveSettings(); 
        //is done with AJAX call, therefore no output is needed
        //header("Location: " . $_SERVER["PHP_SELF"]);
        die();
    }



$webUi = new WebUiGenerator(MENU_SETTINGS);
$webUi->addHeader();
$webUi->addCard("General Settings",getHtmlSettingsGeneral());
$webUi->addCard("Grocy API",getHtmlSettingsGrocyApi());
$isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
$webUi->addCard("Websocket Server Status",getHtmlSettingsWebsockets());
$webUi->addHtml(getHtmlSettingsHiddenValues());
$webUi->addFooter();
$webUi->printHtml();



//Called when settings were saved. For each input, the setting
//is saved as a database entry
function saveSettings() {
    global $BBCONFIG;
    global $db;
    foreach ($BBCONFIG as $key => $value) {
        if (isset($_POST[$key])) {
            if ($_POST[$key] != $value) {
                $value = sanitizeString($_POST[$key]);
                if (stringStartsWith($key, "BARCODE_")) {
                    $db->updateConfig($key, strtoupper($value));
                } else {
                    $db->updateConfig($key, $value);
                }
            }
        } else {
            if (isset($_POST[$key . "_hidden"]) && $_POST[$key . "_hidden"] != $value) {
                $db->updateConfig($key, sanitizeString($_POST[$key . "_hidden"]));
            }
        }
    }
}
?>
