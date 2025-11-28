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
require_once __DIR__ . "/../incl/websocket/client_internal.php";
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
 *
 * @return void
 */
function saveSettings(): void {
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
    
    // Save provider settings
    $providers = BarcodeLookup::getProviders();
    foreach ($providers as $provider) {
        $provider->saveSettings($_POST);
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
    $html->addCheckbox("SAVE_BARCODE_NAME", "Save name from lookup to barcode", $config["SAVE_BARCODE_NAME"], false, false);
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

    $providers = BarcodeLookup::getProviders();
    $providerList = [];

    // Generic JS handler for toggling config fields
    $html->addScript('
        function toggleConfigField(checkbox, fieldId) {
            var field = document.getElementById(fieldId);
            if (!field) return;
            
            if (checkbox.checked) {
                field.disabled = false;
                field.parentElement.classList.remove("is-disabled");
                field.required = true;
            } else {
                field.disabled = true;
                field.parentElement.classList.add("is-disabled");
                field.required = false;
                // Clear validation error if present
                field.parentElement.classList.remove("is-invalid");
            }
        }
    ');

    foreach ($providers as $provider) {
        $configFieldId = $provider->getConfigFieldId();
        
        if ($configFieldId) {
            // Use CheckBoxBuilder for providers with config to attach JS handler
            $checkbox = (new CheckBoxBuilder(
                $provider->getConfigKey(),
                $provider->getName(),
                (string)$provider->isEnabled(),
                $html
            ))->onCheckChanged("toggleConfigField(this, '$configFieldId')")
              ->generate(true);
        } else {
            // Use simple addCheckbox for providers without config (matches original convention)
            $checkbox = $html->addCheckbox(
                $provider->getConfigKey(),
                $provider->getName(),
                (string)$provider->isEnabled(),
                false,
                false,
                true
            );
        }

        $configHtml = $provider->getConfigHtml($html);
        $description = $provider->getDescription();

        // Combine description and config HTML
        $body = $description;
        if (!empty($configHtml)) {
            $body .= "<br/><br/>" . $configHtml;
        }

        // Manually construct list item to allow for auto height and proper vertical stacking
        /** @var string $checkbox */
        $providerList["id" . $provider->getId()] = "
        <li data-id=\"" . $provider->getId() . "\" class=\"mdl-list__item\" style=\"height:auto; min-height: auto; padding: 16px; display: block;\" data-value=\"" . $provider->getId() . "\">
            <div style=\"display: flex; align-items: center;\">
                " . $checkbox . "
            </div>
            <div style=\"padding-left: 28px; margin-top: 4px; color: #757575;\">
                " . $description . "
                " . (!empty($configHtml) ? "<div style=\"margin-top: 12px;\">" . $configHtml . "</div>" : "") . "
            </div>
        </li>";
    }

    $orderAsArray = explode(",", $config["LOOKUP_ORDER"]);
    foreach ($orderAsArray as $orderId) {
        if (isset($providerList["id" . $orderId])) {
            $html->addHtml($providerList["id" . $orderId]);
            unset($providerList["id" . $orderId]);
        }
    }

    // Add any remaining providers that might not be in the order list yet
    foreach ($providerList as $item) {
        $html->addHtml($item);
    }

    $html->addHtml('</ul>');
    $html->addLineBreak();
    
    $html->addHiddenField("LOOKUP_ORDER", $config["LOOKUP_ORDER"]);

    $html->addScript("var elements = document.getElementById('providers');
                           var sortable = Sortable.create(elements, { animation: 150,
                                    dataIdAttr: 'data-id',
                                    onSort: function (evt) {
                                       document.getElementById('LOOKUP_ORDER').value = sortable.toArray().join();
                                    },});");

    return $html->getHtml();
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

function checkRedisConnection(UiEditor &$html): void {
    $error = null;
    try {
        $connected = RedisConnection::ping();
    } catch (Exception $error) {
        $error     = $error->getMessage();
        $connected = false;
    }
    if (!$connected) {
        if ($error == null)
            $error = RedisConnection::getErrorMessage();
        $html->addHtml('<span style="color:red">Cannot connect to Rediscache! ' . $error . '</span>');
    } else {
        $html->addHtml('<span style="color:green">Redis cache is available.</span>');
        $html->addSpaces(4);
        $html->addButton("updatecache", "Update Cache", "updateRedisCacheAndFederation(true)");
    }
}


/**
 * @return string
 */
function getHtmlSettingsWebsockets(): string {
    global $CONFIG;
    $client = new SocketClient('127.0.0.1', $CONFIG->PORT_WEBSOCKET_SERVER);
    if ($client->connect() !== false) {
        return '<span style="color:green">Websocket server is running.</span>';
    } else {
        return '<span style="color:red">Websocket server is not running! ' . $client->getLastError() . '</span>';
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
    $html->addLineBreak();
    $html->buildEditField('REDIS_PW', 'Redis Password', $config["REDIS_PW"])
        ->setPlaceholder('leave blank if none set')
        ->required(false)
        ->type("password")
        ->generate();
    if ($config["USE_REDIS"]) {
        $html->addLineBreak(2);
        checkRedisConnection($html);
    }
    return $html->getHtml();
}
