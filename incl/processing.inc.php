<?php
/**
 * Barcode Buddy for Grocy
 *
 * PHP version 7
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 *
 * Helper file for processing functions
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */

require_once __DIR__ . "/lockGenerator.inc.php";
require_once __DIR__ . "/db.inc.php";
require_once __DIR__ . "/config.inc.php";
require_once __DIR__ . "/lookupProviders/BarcodeLookup.class.php";


// Function that is called when a barcode is passed on
function processNewBarcode($barcodeInput, $bestBeforeInDays = null, $price = null) {
    $db     = DatabaseConnection::getInstance();
    $config = BBConfig::getInstance();

    $barcode = strtoupper($barcodeInput);
    if ($barcode == $config["BARCODE_C"]) {
        $db->setTransactionState(STATE_CONSUME);
        return createLogModeChange(STATE_CONSUME);
    }
    if ($barcode == $config["BARCODE_CS"]) {
        $db->setTransactionState(STATE_CONSUME_SPOILED);
        return createLogModeChange(STATE_CONSUME_SPOILED);
    }
    if ($barcode == $config["BARCODE_CA"]) {
        $db->setTransactionState(STATE_CONSUME_ALL);
        return createLogModeChange(STATE_CONSUME_ALL);
    }
    if ($barcode == $config["BARCODE_P"]) {
        $db->setTransactionState(STATE_PURCHASE);
        return createLogModeChange(STATE_PURCHASE);
    }
    if ($barcode == $config["BARCODE_O"]) {
        $db->setTransactionState(STATE_OPEN);
        return createLogModeChange(STATE_OPEN);
    }
    if ($barcode == $config["BARCODE_GS"]) {
        $db->setTransactionState(STATE_GETSTOCK);
        return createLogModeChange(STATE_GETSTOCK);
    }
    if ($barcode == $config["BARCODE_AS"]) {
        $db->setTransactionState(STATE_ADD_SL);
        return createLogModeChange(STATE_ADD_SL);
    }
    if (stringStartsWith($barcode, $config["BARCODE_Q"])) {
        $quantity = str_replace($config["BARCODE_Q"], "", $barcode);
        checkIfNumeric($quantity);
        if ($config["LAST_PRODUCT"] != null) {
            $lastBarcode = $config["LAST_BARCODE"] . " (" . $config["LAST_PRODUCT"] . ")";
        } else {
            $lastBarcode = $config["LAST_BARCODE"];
        }
        changeQuantityAfterScan($quantity);
        $log = new LogOutput("Set quantity to $quantity for barcode $lastBarcode", EVENT_TYPE_MODE_CHANGE);
        return $log->setVerbose()->createLog();
    }
    if (trim($barcode) == "") {
        $log = new LogOutput("Invalid barcode found", EVENT_TYPE_ERROR);
        return $log->setVerbose()->setWebsocketResultCode(WS_RESULT_PRODUCT_UNKNOWN)->createLog();
    }

    if ($db->isChoreBarcode($barcode)) {
        $choreText = processChoreBarcode($barcode);
        $log       = new LogOutput("Executed chore: " . $choreText, EVENT_TYPE_EXEC_CHORE);
        return $log->setVerbose()->createLog();
    }

    $sanitizedBarcode = sanitizeString($barcode);
    $lockGenerator    = new LockGenerator();
    $lockGenerator->createLock();
    $productInfo = API::getProductByBardcode($sanitizedBarcode);
    if ($productInfo == null) {
        $db->saveLastBarcode($sanitizedBarcode);
        return processUnknownBarcode($sanitizedBarcode, true, $lockGenerator, $bestBeforeInDays, $price);
    } else {
        $db->saveLastBarcode($sanitizedBarcode, $productInfo["name"]);
        return processKnownBarcode($productInfo, $sanitizedBarcode, true, $lockGenerator, $bestBeforeInDays, $price);
    }
}

function createLogModeChange($state) {
    $text = "Set state to ";
    switch ($state) {
        case STATE_CONSUME:
            $text .= "Consume";
            break;
        case STATE_CONSUME_SPOILED:
            $text .= "Consume (spoiled)";
            break;
        case STATE_PURCHASE:
            $text .= "Purchase";
            break;
        case STATE_OPEN:
            $text .= "Open";
            break;
        case STATE_GETSTOCK:
            $text .= "Inventory";
            break;
        case STATE_ADD_SL:
            $text .= "Shopping List";
            break;
        case STATE_CONSUME_ALL:
            $text .= "Consume all";
            break;
        default:
            throw new Exception("Invalid state");
    }
    $log = new LogOutput($text, EVENT_TYPE_MODE_CHANGE);
    return $log->setVerbose()->createLog();
}


//Event types used for plugins
const EVENT_TYPE_ERROR               = -1;
const EVENT_TYPE_MODE_CHANGE         = 0;
const EVENT_TYPE_CONSUME             = 1;
const EVENT_TYPE_CONSUME_S           = 2;
const EVENT_TYPE_PURCHASE            = 3;
const EVENT_TYPE_OPEN                = 4;
const EVENT_TYPE_INVENTORY           = 5;
const EVENT_TYPE_EXEC_CHORE          = 6;
const EVENT_TYPE_ADD_KNOWN_BARCODE   = 7;
const EVENT_TYPE_ADD_NEW_BARCODE     = 8;
const EVENT_TYPE_ADD_UNKNOWN_BARCODE = 9;
const EVENT_TYPE_CONSUME_PRODUCT     = 10;
const EVENT_TYPE_CONSUME_S_PRODUCT   = 11;
const EVENT_TYPE_PURCHASE_PRODUCT    = 12;
const EVENT_TYPE_OPEN_PRODUCT        = 13;
const EVENT_TYPE_GET_STOCK_PRODUCT   = 14;
const EVENT_TYPE_ADD_TO_SHOPPINGLIST = 15;
const EVENT_TYPE_ASSOCIATE_PRODUCT   = 16;
const EVENT_TYPE_ACTION_REQUIRED     = 17;
const EVENT_TYPE_CONSUME_ALL_PRODUCT = 18;


const WS_RESULT_PRODUCT_FOUND     = 0;
const WS_RESULT_PRODUCT_LOOKED_UP = 1;
const WS_RESULT_PRODUCT_UNKNOWN   = 2;
const WS_RESULT_MODE_CHANGE       = 4;
const WS_RESULT_ERROR             = 'E';


//Execute a chore when chore barcode was submitted
function processChoreBarcode($barcode) {
    $id = DatabaseConnection::getInstance()->getChoreBarcode(sanitizeString($barcode))['choreId'];
    checkIfNumeric($id);
    API::executeChore($id);
    return sanitizeString(API::getChoresInfo($id)["name"]);
}

//If grocy does not know this barcode
function processUnknownBarcode($barcode, $websocketEnabled, &$fileLock, $bestBeforeInDays, $price) {
    $db     = DatabaseConnection::getInstance();
    $amount = 1;
    if ($db->getTransactionState() == STATE_PURCHASE) {
        $amount = $db->getQuantityByBarcode($barcode);
    }
    if ($db->isUnknownBarcodeAlreadyStored($barcode)) {
        //Unknown barcode already in local database
        $db->addQuantitiyToUnknownBarcode($barcode, $amount);
        $log    = new LogOutput("Unknown product already scanned. Increasing quantity.", EVENT_TYPE_ADD_NEW_BARCODE, $barcode);
        $output = $log
            ->insertBarcodeInWebsocketText()
            ->setSendWebsocket($websocketEnabled)
            ->setWebsocketResultCode(WS_RESULT_PRODUCT_LOOKED_UP)
            ->createLog();
    } else {
        $productname = "N/A";
        if (is_numeric($barcode)) {
            $productname = BarcodeLookup::lookup($barcode);
        }
        if ($productname != "N/A") {
            $db->insertUnrecognizedBarcode($barcode, $amount, $bestBeforeInDays, $price, $productname, $db->checkNameForTags($productname));
            $log    = new LogOutput("Unknown barcode looked up, found name: " . $productname, EVENT_TYPE_ADD_NEW_BARCODE, $barcode);
            $output = $log
                ->insertBarcodeInWebsocketText()
                ->setSendWebsocket($websocketEnabled)
                ->setCustomWebsocketText($productname)
                ->setWebsocketResultCode(WS_RESULT_PRODUCT_LOOKED_UP)
                ->createLog();
        } else {
            $db->insertUnrecognizedBarcode($barcode, $amount, $bestBeforeInDays, $price);
            $log    = new LogOutput("Unknown barcode could not be looked up.", EVENT_TYPE_ADD_UNKNOWN_BARCODE, $barcode);
            $output = $log
                ->insertBarcodeInWebsocketText()
                ->setSendWebsocket($websocketEnabled)
                ->setCustomWebsocketText($barcode)
                ->setWebsocketResultCode(WS_RESULT_PRODUCT_UNKNOWN)
                ->createLog();
        }
    }
    $fileLock->removeLock();
    return $output;
}


//Convert state to string for websocket server
function stateToString($state) {
    $allowedModes = array(
        STATE_CONSUME         => "Consume",
        STATE_CONSUME_SPOILED => "Consume(spoiled)",
        STATE_PURCHASE        => "Purchase",
        STATE_OPEN            => "Open",
        STATE_GETSTOCK        => "Inventory",
        STATE_ADD_SL          => "Add to shoppinglist"
    );
    return $allowedModes[$state];
}

function getProductByIdFromVariable($id, $products) {
    foreach ($products as $product) {
        if ($product["id"] == $id)
            return $product;
    }
    return null;
}

function changeWeightTareItem($barcode, $newWeight) {
    $product = API::getProductByBardcode($barcode);

    if (($product["stockAmount"] + $product["tareWeight"]) == $newWeight) {
        $log = new LogOutput("Weight unchanged for: " . $product["name"], EVENT_TYPE_ACTION_REQUIRED);
        $log->setVerbose()->dontSendWebsocket()->createLog();
        return true;
    }
    if ($newWeight < $product["tareWeight"]) {
        $log = new LogOutput("Entered weight for " . $product["name"] . " is below tare weight(" . $product["tareWeight"] . ")", EVENT_TYPE_ACTION_REQUIRED);
        $log->setVerbose()->dontSendWebsocket()->createLog();
        return false;
    }

    if ($product["stockAmount"] > ($newWeight - $product["tareWeight"])) {
        API::consumeProduct($product["id"], $newWeight);
    } else {
        API::purchaseProduct($product["id"], $newWeight);
    }
    $log = new LogOutput("Weight set to " . $newWeight . " for: " . $product["name"], EVENT_TYPE_ACTION_REQUIRED);
    $log->setVerbose()->dontSendWebsocket()->createLog();
    return true;
}

//Change mode if was supplied by GET parameter
function processModeChangeGetParameter($modeParameter) {
    $db = DatabaseConnection::getInstance();
    switch (trim($modeParameter)) {
        case "consume":
            $db->setTransactionState(STATE_CONSUME);
            break;
        case "consume_s":
            $db->setTransactionState(STATE_CONSUME_SPOILED);
            break;
        case "purchase":
            $db->setTransactionState(STATE_PURCHASE);
            break;
        case "open":
            $db->setTransactionState(STATE_OPEN);
            break;
        case "inventory":
            $db->setTransactionState(STATE_GETSTOCK);
            break;
        case "shoppinglist":
            $db->setTransactionState(STATE_ADD_SL);
            break;
    }
}


//This will be called when a new grocy product is created from BB and the grocy tab is closed
function processRefreshedBarcode($barcode) {
    $productInfo = API::getProductByBardcode($barcode);
    if ($productInfo != null) {
        DatabaseConnection::getInstance()->updateSavedBarcodeMatch($barcode, $productInfo["id"]);
    }
}

//We are using the old inventory API until the next Grocy version is officially released
const USE_NEW_INVENTORY_API = false;

// Process a barcode that Grocy already knows
function processKnownBarcode($productInfo, $barcode, $websocketEnabled, &$fileLock, $bestBeforeInDays, $price) {
    $config = BBConfig::getInstance();
    $db     = DatabaseConnection::getInstance();

    if ($productInfo["isTare"]) {
        if (!$db->isUnknownBarcodeAlreadyStored($barcode))
            $db->insertActionRequiredBarcode($barcode, $bestBeforeInDays, $price);
        $fileLock->removeLock();
        $log = new LogOutput("Action required: Enter weight for " . $productInfo["name"], EVENT_TYPE_ACTION_REQUIRED, $barcode);
        return $log->setWebsocketResultCode(WS_RESULT_PRODUCT_FOUND)->createLog();
    }


    $state = $db->getTransactionState();

    switch ($state) {
        case STATE_CONSUME:
            $amountToConsume = getQuantityForBarcode($barcode, true, $productInfo);

            if ($productInfo["stockAmount"] > 0) {
                if ($productInfo["stockAmount"] < $amountToConsume)
                    $amountToConsume = $productInfo["stockAmount"];
                $log    = new LogOutput("Consuming " . $amountToConsume . " " . $productInfo["unit"] . " of " . $productInfo["name"], EVENT_TYPE_ADD_KNOWN_BARCODE, $barcode);
                $output = $log
                    ->addStockToText($productInfo["stockAmount"] - $amountToConsume)
                    ->setWebsocketResultCode(WS_RESULT_PRODUCT_FOUND)
                    ->addProductFoundText()
                    ->createLog();
                API::consumeProduct($productInfo["id"], $amountToConsume, false);
                $fileLock->removeLock();
                return $output;
            } else {
                $fileLock->removeLock();
                $log = new LogOutput("None in stock, not consuming: " . $productInfo["name"], EVENT_TYPE_ADD_KNOWN_BARCODE, $barcode);
                return $log
                    ->setWebsocketResultCode(WS_RESULT_PRODUCT_FOUND)
                    ->addProductFoundText()
                    ->createLog();
            }
        case STATE_CONSUME_ALL:
            $amountToConsume = $productInfo["stockAmount"];
            if ($productInfo["stockAmount"] > 0) {
                $log    = new LogOutput("Consuming all" . $amountToConsume . " " . $productInfo["unit"] . " of " . $productInfo["name"], EVENT_TYPE_ADD_KNOWN_BARCODE, $barcode);
                $output = $log
                    ->setWebsocketResultCode(WS_RESULT_PRODUCT_FOUND)
                    ->addProductFoundText()
                    ->createLog();

                API::consumeProduct($productInfo["id"], $amountToConsume, false);
            } else {
                $log    = new LogOutput("None in stock, not consuming: " . $productInfo["name"], EVENT_TYPE_ADD_KNOWN_BARCODE, $barcode);
                $output = $log
                    ->setWebsocketResultCode(WS_RESULT_PRODUCT_FOUND)
                    ->addProductFoundText()
                    ->createLog();
            }
            if ($config["REVERT_SINGLE"]) {
                $db->saveLog("Reverting back to Consume", true);
                $db->setTransactionState(STATE_CONSUME);
            }
            $fileLock->removeLock();
            return $output;
        case STATE_CONSUME_SPOILED:
            //TODO respect quantity factor
            if ($productInfo["stockAmount"] > 0) {
                $log    = new LogOutput("Consuming 1 spoiled " . $productInfo["unit"] . " of " . $productInfo["name"], EVENT_TYPE_ADD_KNOWN_BARCODE, $barcode);
                $output = $log
                    ->addStockToText($productInfo["stockAmount"] - 1)
                    ->setWebsocketResultCode(WS_RESULT_PRODUCT_FOUND)
                    ->addProductFoundText()
                    ->createLog();
                API::consumeProduct($productInfo["id"], 1, true);
            } else {
                $log    = new LogOutput("Product found . None in stock, not consuming: " . $productInfo["name"], EVENT_TYPE_ADD_KNOWN_BARCODE, $barcode);
                $output = $log
                    ->setWebsocketResultCode(WS_RESULT_PRODUCT_FOUND)
                    ->addProductFoundText()
                    ->createLog();
            }
            $fileLock->removeLock();
            if ($config["REVERT_SINGLE"]) {
                $db->saveLog("Reverting back to Consume", true);
                $db->setTransactionState(STATE_CONSUME);
            }
            return $output;
        case STATE_PURCHASE:
            $amount = getQuantityForBarcode($barcode, false, $productInfo);
            if ($productInfo["defaultBestBefore"] == 0 && $bestBeforeInDays == null)
                $additionalLog = " [WARNING]: No default best before date set!";
            else
                $additionalLog = "";
            $log    = new LogOutput("Adding  $amount " . $productInfo["unit"] . " of " . $productInfo["name"] . $additionalLog, EVENT_TYPE_ADD_KNOWN_BARCODE, $barcode);
            $output = $log
                ->addStockToText($productInfo["stockAmount"] + $amount)
                ->setWebsocketResultCode(WS_RESULT_PRODUCT_FOUND)
                ->addProductFoundText()
                ->createLog();
            API::purchaseProduct($productInfo["id"], $amount, $bestBeforeInDays, $price, $fileLock, $productInfo["defaultBestBefore"]);
            //no $fileLock->removeLock() needed, as it is done in API::purchaseProduct
            return $output;
        case STATE_OPEN:
            if ($productInfo["stockAmount"] > 0) {
                $log    = new LogOutput("Opening 1 " . $productInfo["unit"] . " of " . $productInfo["name"], EVENT_TYPE_ADD_KNOWN_BARCODE, $barcode);
                $output = $log
                    ->addStockToText($productInfo["stockAmount"])
                    ->setWebsocketResultCode(WS_RESULT_PRODUCT_FOUND)
                    ->addProductFoundText()
                    ->createLog();
                API::openProduct($productInfo["id"]);
            } else {
                $log    = new LogOutput("Product found . None in stock, not opening: " . $productInfo["name"], EVENT_TYPE_ADD_KNOWN_BARCODE, $barcode);
                $output = $log
                    ->setWebsocketResultCode(WS_RESULT_PRODUCT_FOUND)
                    ->addProductFoundText()
                    ->createLog();
            }
            $fileLock->removeLock();
            if ($config["REVERT_SINGLE"]) {
                $db->saveLog("Reverting back to Consume", true);
                $db->setTransactionState(STATE_CONSUME);
            }
            return $output;
        case STATE_GETSTOCK:
            $fileLock->removeLock();
            $log = "Currently in stock: " . $productInfo["stockAmount"] . " " . $productInfo["unit"] . " of " . $productInfo["name"];
            if (USE_NEW_INVENTORY_API) {
                if ($productInfo["stockAmount"] > 0) {
                    $locationInfo = API::getProductLocations($productInfo["id"]);
                    foreach ($locationInfo as $location) {
                        $log = $log . "\nLocation " . $location["location_name"] . ": " . $location["amount"] . " " . $productInfo["unit"];
                    }
                }
            }
            return (new LogOutput($log, EVENT_TYPE_ADD_KNOWN_BARCODE))->createLog();
        case STATE_ADD_SL:
            $fileLock->removeLock();
            return (new LogOutput("Added to shopping list: 1 " . $productInfo["unit"] . " of " . $productInfo["name"], EVENT_TYPE_ADD_KNOWN_BARCODE))->createLog();
            API::addToShoppinglist($productInfo["id"], 1);
            return $output;
        default:
            throw new Exception("Unknown state");
    }
}

function getQuantityForBarcode($barcode, $isConsume, $productInfo) {
    $config = BBConfig::getInstance();

    if ($isConsume && !$config["CONSUME_SAVED_QUANTITY"])
        return 1;
    if ($config["USE_GROCY_QU_FACTOR"])
        return intval($productInfo["quFactor"]);
    return $amount = DatabaseConnection::getInstance()->getQuantityByBarcode($barcode);
}


//Function for generating the <select> elements in the web ui
function printSelections($selected, $productinfo) {
    $optionscontent = " <option value = \"0\" >= None =</option>";
    if (!isset($productinfo) || !sizeof($productinfo))
        return $optionscontent;

    $selections = array();
    foreach ($productinfo as $product) {
        $selections[$product["id"]] = $product["name"];
    }

    natcasesort($selections);
    foreach ($selections as $key => $val) {
        if ($key != $selected) {
            $optionscontent = $optionscontent . "<option value=\"" . $key . "\">" . $val . "</option>";
        } else {
            $optionscontent = $optionscontent . "<option value=\"" . $key . "\" selected >" . $val . "</option>";
        }
    }

    return $optionscontent;
}

//Sanitizes a string for database input
function sanitizeString($input, $strongFilter = false) {
    if ($strongFilter) {
        return filter_var($input, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
    } else {
        return filter_var($input, FILTER_SANITIZE_STRING);
    }
}

//Terminates script if non numeric
function checkIfNumeric($input) {
    if (!is_numeric($input)) {
        die("Illegal input! " . sanitizeString($input) . " needs to be a number");
    }
}

//Generate checkboxes for web ui
function explodeWordsAndMakeCheckboxes($words, $id) {
    if ($words == "N/A") {
        return "";
    }
    $selections = "";
    $cleanWords = cleanNameForTagLookup($words);
    $i          = 0;
    $addedWords = array(); // Check if word is used multiple times when creating possible tags
    foreach ($cleanWords as $str) {
        $tagWord = trim($str);
        if (strlen($tagWord) > 0 && !in_array(strtolower($tagWord), $addedWords) && DatabaseConnection::getInstance()->tagNotUsedYet($tagWord)) {
            $selections = $selections . '<label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="checkbox-' . $id . '_' . $i . '">
  <input type="checkbox"  value="' . $tagWord . '" name="tags[' . $id . '][' . $i . ']" id="checkbox-' . $id . '_' . $i . '" class="mdl-checkbox__input">
  <span class="mdl-checkbox__label">' . $tagWord . '</span>
</label>';
            $i++;
            array_push($addedWords, strtolower($tagWord));
        }
    }
    return $selections;
}

function cleanNameForTagLookup($input) {
    $ignoreChars = array(",", ".", "-", ":", "(", ")");
    $cleanWords  = str_replace($ignoreChars, " ", $input);
    return explode(' ', $cleanWords);
}

//If a quantity barcode was scanned, add the quantitiy and process further
function changeQuantityAfterScan($amount) {
    $config = BBConfig::getInstance();

    $db      = DatabaseConnection::getInstance();
    $barcode = sanitizeString($config["LAST_BARCODE"]);
    if ($config["LAST_PRODUCT"] != null) {
        $db->addUpdateQuantitiy($barcode, $amount, $config["LAST_PRODUCT"]);
    } else {
        $product = API::getProductByBardcode($barcode);
        if ($product != null) {
            $db->addUpdateQuantitiy($barcode, $amount, $product["name"]);
        } else {
            $db->addUpdateQuantitiy($barcode, $amount);
        }
    }
    if ($db->getStoredBarcodeAmount($barcode) == 1) {
        $db->setQuantitiyToUnknownBarcode($barcode, $amount);
    }
}


//Merge tags and product info
function getAllTags() {
    $tags       = DatabaseConnection::getInstance()->getStoredTags();
    $products   = API::getProductInfo();
    $returnTags = array();

    foreach ($tags as $tag) {
        foreach ($products as $product) {
            if ($product["id"] == $tag["itemId"]) {
                $tag["item"] = $product["name"];
                array_push($returnTags, $tag);
                break;
            }
        }
    }
    usort($returnTags, "sortTags");
    return $returnTags;
}

//Sorts the tags by name
function sortTags($a, $b) {
    return $a['item'] > $b['item'];
}


//Sorts the chores by name
function sortChores($a, $b) {
    return $a['name'] > $b['name'];
}


//Merges chores with chore info
function getAllChores() {
    $chores       = API::getChoresInfo();
    $barcodes     = DatabaseConnection::getInstance()->getStoredChoreBarcodes();
    $returnChores = array();

    foreach ($chores as $chore) {
        $chore["barcode"] = null;
        foreach ($barcodes as $barcode) {
            if ($chore["id"] == $barcode["choreId"]) {
                $chore["barcode"] = $barcode["barcode"];
                break;
            }
        }
        array_push($returnChores, $chore);
    }
    usort($returnChores, "sortChores");
    return $returnChores;
}

//Returns true if string starts with $startString
function stringStartsWith($string, $startString) {
    $len = strlen($startString);
    return (substr($string, 0, $len) === $startString);
}


//Trim string
function strrtrim($message, $strip) {
    // break message apart by strip string 
    $lines = explode($strip, $message);
    $last  = '';
    // pop off empty strings at the end 
    do {
        $last = array_pop($lines);
    } while (empty($last) && (count($lines)));
    // re-assemble what remains 
    return implode($strip, array_merge($lines, array($last)));
}

function generateRandomString($length = 30) {
    return substr(str_shuffle(str_repeat($x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length / strlen($x)))), 1, $length);
}

function getApiUrl($removeAfter) {
    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

    $url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    return $requestedUrl = trim(substr($url, 0, strpos($url, $removeAfter))) . "api/";
}

function showErrorNotWritable($error = "DB Error") {
    die($error . ": Database file cannot be created, as folder or database file is not writable. Please check your permissions.<br>
             Have a look at this link to find out how to do this:
             <a href='https://github.com/olab/Open-Labyrinth/wiki/How-do-I-make-files-and-folders-writable-for-the-web-server%3F'>" . "How do I make files and folders writable for the web server?</a>");
}

class LogOutput {

    private $logText;
    private $eventType;
    private $barcode = null;
    private $isVerbose = false;
    private $sendWebsocketMessage = true;
    private $websocketResultCode = WS_RESULT_PRODUCT_FOUND;
    private $websocketText;

    function __construct($logText, $eventType, $barcode = null) {
        $this->logText       = $logText;
        $this->eventType     = $eventType;
        $this->websocketText = $logText;
        $this->barcode       = $barcode;

        if ($barcode != null)
            $this->logText .= " Barcode: $barcode";
    }

    public function setVerbose() {
        $this->isVerbose = true;
        return $this;
    }

    public function insertBarcodeInWebsocketText() {
        if ($this->barcode != null)
            $this->websocketText .= " Barcode: $this->barcode";
        return $this;
    }

    public function dontSendWebsocket() {
        $this->sendWebsocketMessage = false;
        return $this;
    }

    public function addStockToText($amount) {
        if (!BBConfig::getInstance()["SHOW_STOCK_ON_SCAN"])
            return $this;
        //Do not have "." at the beginning if last character was "!"
        if (substr($this->logText, -1) != "!")
            $this->logText .= ".";
        if (substr($this->websocketText, -1) != "!")
            $this->websocketText .= ".";
        $text = " In Stock: $amount";

        $this->logText       .= $text;
        $this->websocketText .= $text;
        return $this;
    }


    public function addProductFoundText() {
        $this->logText = "Product found. " . $this->logText;
        return $this;
    }

    public function setSendWebsocket($sendWebsocket) {
        $this->sendWebsocketMessage = $sendWebsocket;
        return $this;
    }

    public function setWebsocketResultCode($code) {
        $this->websocketResultCode = $code;
        return $this;
    }

    public function setCustomWebsocketText($text) {
        $this->websocketText = $text;
        return $this;
    }

    public function createLog() {
        global $LOADED_PLUGINS;

        DatabaseConnection::getInstance()->saveLog($this->logText, $this->isVerbose);
        if ($this->sendWebsocketMessage) {
            sendWebsocketMessage($this->websocketText, $this->websocketResultCode);
        }
        if (in_array("EventReceiver", $LOADED_PLUGINS)) {
            pluginEventReceiver_processEvent($this->eventType, $this->logText);
        }
        return $this->logText;
    }


}