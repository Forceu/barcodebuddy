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



require_once __DIR__ . "/../incl/config.php";
require_once __DIR__ . "/../incl/api.inc.php";
require_once __DIR__ . "/../incl/db.inc.php";
require_once __DIR__ . "/../incl/processing.inc.php";
require_once __DIR__ . "/../incl/websocketconnection.inc.php";
require_once __DIR__ . "/../incl/sse/websocket_client.php"; 
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




function getHtmlSettingsGeneral() {
    global $BBCONFIG;
    $html = new UiEditor(true, null, "settings1");
    $html->addHtml('<div class="flex-settings">');
    $html->addDiv($html->buildEditField("BARCODE_C", "Barcode: Consume", $BBCONFIG["BARCODE_C"])->generate(true), null, "flex-settings-child");
    $html->addDiv($html->buildEditField("BARCODE_CS", "Barcode: Consume (spoiled)", $BBCONFIG["BARCODE_CS"])->generate(true), null, "flex-settings-child");
    $html->addDiv($html->buildEditField("BARCODE_P", "Barcode: Purchase", $BBCONFIG["BARCODE_P"])->generate(true), null, "flex-settings-child");
    $html->addDiv($html->buildEditField("BARCODE_O", "Barcode: Open", $BBCONFIG["BARCODE_O"])->generate(true), null, "flex-settings-child");
    $html->addDiv($html->buildEditField("BARCODE_GS", "Barcode: Inventory", $BBCONFIG["BARCODE_GS"])->generate(true), null, "flex-settings-child");
    $html->addDiv($html->buildEditField("BARCODE_Q", "Barcode: Quantitiy", $BBCONFIG["BARCODE_Q"])->generate(true), null, "flex-settings-child");
    $html->addDiv($html->buildEditField("BARCODE_AS", "Barcode: Add to shopping list", $BBCONFIG["BARCODE_AS"])->generate(true), null, "flex-settings-child");
    $html->addDiv($html->buildEditField("REVERT_TIME", "Revert state to &quot;Consume&quot; after time passed in minutes", $BBCONFIG["REVERT_TIME"])
                                            ->pattern('-?[0-9]*(\.[0-9]+)?')
                                            ->onKeyPress('return (event.charCode == 8 || event.charCode == 0) ? null : event.charCode >= 48 && event.charCode <= 57')
                                            ->generate(true)
                                        , null, "flex-settings-child");
    $html->addHtml('</div>');
    $html->addLineBreak();

    $html->addCheckbox("REVERT_SINGLE", "Revert after single item scan in &quot;Open&quot; or &quot;Spoiled&quot; mode", $BBCONFIG["REVERT_SINGLE"], false, false);
    $html->addCheckbox("SHOPPINGLIST_REMOVE", "Remove purchased items from shoppinglist", $BBCONFIG["SHOPPINGLIST_REMOVE"], false, false);
    $html->addCheckbox("WS_FULLSCREEN", "Show Screen module in fullscreen", $BBCONFIG["WS_FULLSCREEN"], false, false);
    $html->addCheckbox("USE_GENERIC_NAME", "Use generic names for lookup", $BBCONFIG["USE_GENERIC_NAME"], false, false);
    $html->addCheckbox("MORE_VERBOSE", "More verbose logs", $BBCONFIG["MORE_VERBOSE"], false, false);
    $html->addLineBreak(2);
    $html->addHtml('<small><i>Hint: You can find picture files of the default barcodes in the &quot;example&quot; folder or <a style="color: inherit;" href="https://github.com/Forceu/barcodebuddy/tree/master/example/defaultBarcodes">online</a></i></small>');
    $html->addHiddenField("isSaved", "1");

    return $html->getHtml();
}


function getHtmlSettingsGrocyApi() {
    global $BBCONFIG;
    $html = new UiEditor(true, null, "settings2");
    $html->buildEditField('GROCY_API_URL', 'Grocy API URL',  $BBCONFIG["GROCY_API_URL"])
                            ->pattern('https://.*/api/|http://.*/api/|https://.*/api|http://.*/api')
                            ->setPlaceholder('e.g. https://your.grocy.com/api/')
                            ->generate();
    $editValue = "";
    $html->buildEditField('GROCY_API_KEY', 'Grocy API Key',  $BBCONFIG["GROCY_API_KEY"])
                            ->pattern('[A-Za-z0-9]{50}')
                            ->generate();
    $html->addLineBreak(2);
    $html->addHtml(checkGrocyConnection());
    return $html->getHtml();
}


function checkGrocyConnection() {
    global $BBCONFIG;
    $result = API::checkApiConnection($BBCONFIG["GROCY_API_URL"], $BBCONFIG["GROCY_API_KEY"]);
    if ($result === true) {
        return  '<span style="color:green">Can connect to Grocy, valid API key.</span>';
    } else {
        return  '<span style="color:red">Unable to connect to Grocy! '.$result .'</span>';
    }
}


function getHtmlSettingsWebsockets() {
    $sp = websocket_open('localhost', PORT_WEBSOCKET_SERVER, '', $errorstr, 5);
    if ($sp !== false) {
        return  '<span style="color:green">Websocket server is running.</span>';
    } else {
        return  '<span style="color:red">Websocket server is not running! '.$errorstr.'</span>';
    }
}

?>
