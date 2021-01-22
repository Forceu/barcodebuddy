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
$webUi->addCard("Redis Cache", getHtmlSettingsRedis());
$webUi->addCard("Websocket Server Status", getHtmlSettingsWebsockets());
$webUi->addFooter();
$webUi->printHtml();


/**
 * Called when settings were saved. For each input, the setting
 * is saved as a database entry
 */
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


/**
 * @return string
 */
function getHtmlSettingsGeneral(): string {
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


/**
 * @return string
 */
function getHtmlSettingsGrocyApi(): string {
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

/**
 * @return string
 */
function getHtmlSettingsBarcodeLookup(): string {
    $config = BBConfig::getInstance();
    $html   = new UiEditor(true, null, "settings3");
    $html->addScriptFile("../incl/js/Sortable.min.js");
    $html->addHtml("Use Drag&amp;Drop for changing lookup order");
    $html->addHtml('<ul class="demo-list-item mdl-list" id="providers">');

    $providerList = getProviderListItems($html);
    $orderAsArray = explode(",", $config["LOOKUP_ORDER"]);
    foreach ($orderAsArray as $orderId) {
        $html->addHtml($providerList["id" . $orderId]);
    }


    $html->addHtml('</ul>');
    $html->addLineBreak();
    $html->addHtml((new EditFieldBuilder(
        'LOOKUP_UPC_DATABASE_KEY',
        'UPCDatabase.org API Key',
        $config["LOOKUP_UPC_DATABASE_KEY"],
        $html))
        ->required($config["LOOKUP_USE_UPC_DATABASE"])
        ->pattern('[A-Za-z0-9]{32}')
        ->disabled(!$config["LOOKUP_USE_UPC_DATABASE"])
        ->generate(true)
    );
    $html->addLineBreak();
    $html->addHtml((new EditFieldBuilder(
        'LOOKUP_OPENGTIN_KEY',
        'OpenGtinDb.org API Key',
        $config["LOOKUP_OPENGTIN_KEY"],
        $html))
        ->required($config["LOOKUP_USE_OPEN_GTIN_DATABASE"])
        ->pattern('[^%]{3,}')
        ->disabled(!$config["LOOKUP_USE_OPEN_GTIN_DATABASE"])
        ->generate(true)
    );

    $html->addHiddenField("LOOKUP_ORDER", $config["LOOKUP_ORDER"]);

    $html->addScript("var elements = document.getElementById('providers');
                           var sortable = Sortable.create(elements, { animation: 150,
                                    dataIdAttr: 'data-id',
                                    onSort: function (evt) {
                                       document.getElementById('LOOKUP_ORDER').value = sortable.toArray().join();
                                    },});");

    return $html->getHtml();
}

function generateApiKeyChangeScript(string $functionName, string $keyId): string {
    return "function " . $functionName . "(element) {
                apiEditField = document.getElementById('" . $keyId . "');
                if (!apiEditField) {
                    console.warn('Unable to find element " . $keyId . "');
                } else {
                    apiEditField.required = element.checked;
                    if (element.checked) {
                        apiEditField.parentNode.MaterialTextfield.enable();
                    } else {
                        apiEditField.parentNode.MaterialTextfield.disable();
                    }
                }
            }";
}

function getProviderListItems(UiEditor $html): array {
    $config                                 = BBConfig::getInstance();
    $result                                 = array();
    $result["id" . LOOKUP_ID_OPENFOODFACTS] = $html->addListItem($html->addCheckbox('LOOKUP_USE_OFF', 'Open Food Facts', $config["LOOKUP_USE_OFF"], false, false, true), "Uses OpenFoodFacts.org", LOOKUP_ID_OPENFOODFACTS, true);
    $result["id" . LOOKUP_ID_UPCDB]         = $html->addListItem($html->addCheckbox('LOOKUP_USE_UPC', 'UPC Item DB', $config["LOOKUP_USE_UPC"], false, false, true), "Uses UPCitemDB.com", LOOKUP_ID_UPCDB, true);
    $result["id" . LOOKUP_ID_ALBERTHEIJN]   = $html->addListItem($html->addCheckbox('LOOKUP_USE_AH', 'Albert Heijn', $config["LOOKUP_USE_AH"], false, false, true), "Uses AH.nl", LOOKUP_ID_ALBERTHEIJN, true);
    $result["id" . LOOKUP_ID_JUMBO]         = $html->addListItem($html->addCheckbox('LOOKUP_USE_JUMBO', 'Jumbo', $config["LOOKUP_USE_JUMBO"], false, false, true), "Uses Jumbo.com (slow)", LOOKUP_ID_JUMBO, true);
    $result["id" . LOOKUP_ID_UPCDATABASE]   = $html->addListItem((new CheckBoxBuilder(
        "LOOKUP_USE_UPC_DATABASE",
        "UPC Database",
        $config["LOOKUP_USE_UPC_DATABASE"],
        $html)
    )->onCheckChanged(
        "handleUPCDBChange(this)",
        generateApiKeyChangeScript("handleUPCDBChange", "LOOKUP_UPC_DATABASE_KEY"))
        ->generate(true), "Uses UPCDatabase.org", LOOKUP_ID_UPCDATABASE, true);

    $result["id" . LOOKUP_ID_OPENGTINDB]    = $html->addListItem((new CheckBoxBuilder(
        "LOOKUP_USE_OPEN_GTIN_DATABASE",
        "Open EAN / GTIN Database",
        $config["LOOKUP_USE_OPEN_GTIN_DATABASE"],
        $html)
    )->onCheckChanged(
        "handleOpenGtinChange(this)",
        generateApiKeyChangeScript("handleOpenGtinChange", "LOOKUP_OPENGTIN_KEY"))
        ->generate(true), "Uses OpenGtinDb.org", LOOKUP_ID_OPENGTINDB, true);
    $result["id" . LOOKUP_ID_BBUDDY_SERVER] = $html->addListItem($html->addCheckbox('LOOKUP_USE_BBUDDY_SERVER', 'Barcode Buddy Federation', $config["LOOKUP_USE_BBUDDY_SERVER"], false, false, true), "Uses " . BarcodeServer::HOST_READABLE, LOOKUP_ID_BBUDDY_SERVER, true);
    return $result;
}


/**
 * @return string
 */
function checkGrocyConnection(): string {
    $config = BBConfig::getInstance();
    $result = API::checkApiConnection($config["GROCY_API_URL"], $config["GROCY_API_KEY"]);
    if ($result === true) {
        return '<span style="color:green">Successfully connected to Grocy, valid API key.</span>';
    } else {
        return '<span style="color:red">Unable to connect to Grocy! ' . $result . '</span>';
    }
}

function checkRedisConnection(UiEditor &$html) {
    if (!RedisConnection::isRedisAvailable()) {
        $error = RedisConnection::getErrorMessage();
        $html->addHtml('<span style="color:red">Cannot connect to Rediscache! ' . $error . '</span>');
    } else {
        $html->addHtml('<span style="color:green">Redis cache is available.</span>');
        $html->addSpaces(4);
        $html->addButton("updatecache", "Update Cache", "updateRedisCache(true)");
    }
}


/**
 * @return string
 */
function getHtmlSettingsWebsockets(): string {
    global $CONFIG;
    $sp = websocket_open('localhost', $CONFIG->PORT_WEBSOCKET_SERVER, '', $errorstr, 5);
    if ($sp !== false) {
        return '<span style="color:green">Websocket server is running.</span>';
    } else {
        return '<span style="color:red">Websocket server is not running! ' . $errorstr . '</span>';
    }
}

/**
 * @return string
 */
function getHtmlSettingsRedis(): string {
    $config = BBConfig::getInstance();
    $html   = new UiEditor(true, null, "settings4");
    $html->addCheckbox("USE_REDIS", "Use Redis cache", $config["USE_REDIS"], false, false);
    $html->addLineBreak(1);
    $html->buildEditField('REDIS_IP', 'Redis Server IP', $config["REDIS_IP"])
        ->setPlaceholder('e.g. 127.0.0.1')
        ->generate();
    $html->buildEditField('REDIS_PORT', 'Redis Server Port', $config["REDIS_PORT"])
        ->setPlaceholder('e.g. 6379')
        ->pattern('^([0-9]{1,4}|[1-5][0-9]{4}|6[0-4][0-9]{3}|65[0-4][0-9]{2}|655[0-2][0-9]|6553[0-5])$')
        ->generate();
    if ($config["USE_REDIS"]) {
        $html->addLineBreak(2);
        checkRedisConnection($html);
    }
    return $html->getHtml();
}
