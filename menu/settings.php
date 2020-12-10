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


require_once __DIR__ . "/../incl/configProcessing.inc.php";
require_once __DIR__ . "/../incl/api.inc.php";
require_once __DIR__ . "/../incl/db.inc.php";
require_once __DIR__ . "/../incl/processing.inc.php";
require_once __DIR__ . "/../incl/websocketconnection.inc.php";
require_once __DIR__ . "/../incl/sse/websocket_client.php";
require_once __DIR__ . "/../incl/webui.inc.php";
require_once __DIR__ . "/../incl/config.inc.php";

$CONFIG->checkIfAuthenticated(true, true);


//Save settings
if (isset($_POST["isSaved"])) {
    saveSettings();
    //is done with AJAX call, therefore only "OK" is sent
    echo "OK";
    die();
}


$webUi = new WebUiGenerator(MENU_SETTINGS);
$webUi->addHeader();
$webUi->addCard("General Settings", getHtmlSettingsGeneral());
$webUi->addCard("Barcode Lookup Providers", getHtmlSettingsBarcodeLookup());
$webUi->addCard("Grocy API", getHtmlSettingsGrocyApi());
$webUi->addCard("Websocket Server Status", getHtmlSettingsWebsockets());
$webUi->addFooter();
$webUi->printHtml();


//Called when settings were saved. For each input, the setting
//is saved as a database entry
function saveSettings() {
    $db     = DatabaseConnection::getInstance();
    $config = BBConfig::getInstance();
    foreach ($config as $key => $value) {
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
    $config = BBConfig::getInstance();
    $html   = new UiEditor(true, null, "settings1");
    $html->addHtml('<div class="flex-settings">');
    $html->addDiv($html->buildEditField("BARCODE_C", "Barcode: Consume", $config["BARCODE_C"])->generate(true), null, "flex-settings-child");
    $html->addDiv($html->buildEditField("BARCODE_CS", "Barcode: Consume (spoiled)", $config["BARCODE_CS"])->generate(true), null, "flex-settings-child");
    $html->addDiv($html->buildEditField("BARCODE_CA", "Barcode: Consume all", $config["BARCODE_CA"])->generate(true), null, "flex-settings-child");
    $html->addDiv($html->buildEditField("BARCODE_P", "Barcode: Purchase", $config["BARCODE_P"])->generate(true), null, "flex-settings-child");
    $html->addDiv($html->buildEditField("BARCODE_O", "Barcode: Open", $config["BARCODE_O"])->generate(true), null, "flex-settings-child");
    $html->addDiv($html->buildEditField("BARCODE_GS", "Barcode: Inventory", $config["BARCODE_GS"])->generate(true), null, "flex-settings-child");
    $html->addDiv($html->buildEditField("BARCODE_Q", "Barcode: Quantity", $config["BARCODE_Q"])->generate(true), null, "flex-settings-child");
    $html->addDiv($html->buildEditField("BARCODE_AS", "Barcode: Add to shopping list", $config["BARCODE_AS"])->generate(true), null, "flex-settings-child");
    $html->addDiv($html->buildEditField("REVERT_TIME", "Revert state to &quot;Consume&quot; after time passed in minutes", $config["REVERT_TIME"])
        ->pattern('-?[0-9]*(\.[0-9]+)?')
        ->onKeyPress('return (event.charCode == 8 || event.charCode == 0) ? null : event.charCode >= 48 && event.charCode <= 57')
        ->generate(true)
        , null, "flex-settings-child");
    $html->addHtml('</div>');
    $html->addLineBreak();

    $html->addCheckbox("REVERT_SINGLE", "Revert after single item scan in &quot;Open&quot; or &quot;Spoiled&quot; mode", $config["REVERT_SINGLE"], false, false);
    $html->addCheckbox("SHOPPINGLIST_REMOVE", "Remove purchased items from shoppinglist", $config["SHOPPINGLIST_REMOVE"], false, false);
    $html->addCheckbox("CONSUME_SAVED_QUANTITY", "Consume amount of quantity saved for barcode", $config["CONSUME_SAVED_QUANTITY"], false, false);
    $html->addCheckbox("USE_GROCY_QU_FACTOR", "Use Grocys quantity conversion", $config["USE_GROCY_QU_FACTOR"], false, false);
    $html->addCheckbox("WS_FULLSCREEN", "Show Screen module in fullscreen", $config["WS_FULLSCREEN"], false, false);
    $html->addCheckbox("USE_GENERIC_NAME", "Use generic names for lookup", $config["USE_GENERIC_NAME"], false, false);
    $html->addCheckbox("SHOW_STOCK_ON_SCAN", "Show stock amount on scan", $config["SHOW_STOCK_ON_SCAN"], false, false);
    $html->addCheckbox("MORE_VERBOSE", "More verbose logs", $config["MORE_VERBOSE"], false, false);
    $html->addLineBreak(2);
    $html->addHtml('<small><i>Hint: You can find picture files of the default barcodes in the &quot;example&quot; folder or <a style="color: inherit;" href="https://github.com/Forceu/barcodebuddy/tree/master/example/defaultBarcodes">online</a></i></small>');
    $html->addHiddenField("isSaved", "1");

    return $html->getHtml();
}


function getHtmlSettingsGrocyApi() {
    $config = BBConfig::getInstance();
    $html   = new UiEditor(true, null, "settings2");
    $html->buildEditField('GROCY_API_URL', 'Grocy API URL', $config["GROCY_API_URL"])
        ->pattern('https://.*/api/|http://.*/api/|https://.*/api|http://.*/api')
        ->setPlaceholder('e.g. https://your.grocy.com/api/')
        ->generate();
    $html->buildEditField('GROCY_API_KEY', 'Grocy API Key', $config["GROCY_API_KEY"])
        ->pattern('[A-Za-z0-9]{50}')
        ->generate();
    $html->addLineBreak(2);
    $html->addHtml(checkGrocyConnection());
    return $html->getHtml();
}

function getHtmlSettingsBarcodeLookup() {
    $config = BBConfig::getInstance();
    $html   = new UiEditor(true, null, "settings3");
    $html->addCheckbox('LOOKUP_USE_OFF', 'Use OpenFoodFacts.org', $config["LOOKUP_USE_OFF"]);
    $html->addLineBreak();
    $html->addCheckbox('LOOKUP_USE_UPC', 'Use UPCitemDB.com', $config["LOOKUP_USE_UPC"]);
    $html->addLineBreak();
    $html->addCheckbox('LOOKUP_USE_JUMBO', 'Use Jumbo.com', $config["LOOKUP_USE_JUMBO"]);
    $html->addLineBreak();;
    $html->addHtml(
        (
        new CheckBoxBuilder(
            "LOOKUP_USE_UPC_DATABASE",
            "Use UPC Database",
            $config["LOOKUP_USE_UPC_DATABASE"],
            $html)
        )
            ->onCheckChanged(
            "handleUPCDBChange(this)",
            "function handleUPCDBChange(element) {
                api_key_input = document.querySelector('#LOOKUP_UPC_DATABASE_KEY');
                if (!api_key_input) {
                    console.warn('Unable to fine element LOOKUP_UPC_DATABASE_KEY');
                } else {
                    api_key_input.required = element.checked;
                }
            }")
            ->addSpaces()
            ->generate(true)
    );
    $html->addHtml(
        (new EditFieldBuilder(
            'LOOKUP_UPC_DATABASE_KEY',
            'UPC Database API Key',
            $config["LOOKUP_UPC_DATABASE_KEY"],
            $html)
        )
            ->required($config["LOOKUP_USE_UPC_DATABASE"])
            ->pattern('[A-Za-z0-9]{32}')
            ->generate(true)
    );

    return $html->getHtml();
}


function checkGrocyConnection() {
    $config = BBConfig::getInstance();
    $result = API::checkApiConnection($config["GROCY_API_URL"], $config["GROCY_API_KEY"]);
    if ($result === true) {
        return '<span style="color:green">Successfully connected to Grocy, valid API key.</span>';
    } else {
        return '<span style="color:red">Unable to connect to Grocy! ' . $result . '</span>';
    }
}


function getHtmlSettingsWebsockets() {
    global $CONFIG;
    $sp = websocket_open('localhost', $CONFIG->PORT_WEBSOCKET_SERVER, '', $errorstr, 5);
    if ($sp !== false) {
        return '<span style="color:green">Websocket server is running.</span>';
    } else {
        return '<span style="color:red">Websocket server is not running! ' . $errorstr . '</span>';
    }
}
