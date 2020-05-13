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


// Function that is called when a barcode is passed on
function processNewBarcode($barcodeInput, $websocketEnabled = true, $bestBeforeInDays = null, $price = null) {
    require_once __DIR__ . "/db.inc.php";
    global $db;
    global $BBCONFIG;
    
    $barcode = strtoupper($barcodeInput);
    if ($barcode == $BBCONFIG["BARCODE_C"]) {
        $db->setTransactionState(STATE_CONSUME);
        return outputLog("Set state to Consume", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
    }
    if ($barcode == $BBCONFIG["BARCODE_CS"]) {
        $db->setTransactionState(STATE_CONSUME_SPOILED);
        return outputLog("Set state to Consume (spoiled)", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
    }
    if ($barcode == $BBCONFIG["BARCODE_CA"]) {
        $db->setTransactionState(STATE_CONSUME_ALL);
        return outputLog("Set state to Consume all", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
    }
    if ($barcode == $BBCONFIG["BARCODE_P"]) {
        $db->setTransactionState(STATE_PURCHASE);
        return outputLog("Set state to Purchase", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
    }
    if ($barcode == $BBCONFIG["BARCODE_O"]) {
        $db->setTransactionState(STATE_OPEN);
        return outputLog("Set state to Open", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
    }
    if ($barcode == $BBCONFIG["BARCODE_GS"]) {
        $db->setTransactionState(STATE_GETSTOCK);
        return outputLog("Set state to Inventory", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
    }
    if ($barcode == $BBCONFIG["BARCODE_AS"]) {
        $db->setTransactionState(STATE_ADD_SL);
        return outputLog("Set state to Shopping list", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
    }
    if (stringStartsWith($barcode, $BBCONFIG["BARCODE_Q"])) {
        $quantitiy = str_replace($BBCONFIG["BARCODE_Q"], "", $barcode);
        checkIfNumeric($quantitiy);
        if ($BBCONFIG["LAST_PRODUCT"] != null) {
            $lastBarcode = $BBCONFIG["LAST_BARCODE"] . " (" . $BBCONFIG["LAST_PRODUCT"] . ")";
        } else {
            $lastBarcode = $BBCONFIG["LAST_BARCODE"];
        }
        changeQuantityAfterScan($quantitiy);
        return outputLog("Set quantitiy to $quantitiy for barcode $lastBarcode", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
    }
    if (trim($barcode) == "") {
        return outputLog("Invalid barcode found", EVENT_TYPE_ERROR, true, $websocketEnabled, WS_RESULT_PRODUCT_UNKNOWN);
    }
    
    if ($db->isChoreBarcode($barcode)) {
        $choreText = processChoreBarcode($barcode);
        return outputLog("Executed chore: " . $choreText, EVENT_TYPE_EXEC_CHORE, true, $websocketEnabled);
    }
    
    $sanitizedBarcode = sanitizeString($barcode);
    $lockGenerator    = new LockGenerator();
    $lockGenerator->createLock();
    $productInfo = API::getProductByBardcode($sanitizedBarcode);
    if ($productInfo == null) {
        $db->saveLastBarcode($sanitizedBarcode);
        return processUnknownBarcode($sanitizedBarcode, $websocketEnabled, $lockGenerator);
    } else {
        $db->saveLastBarcode($sanitizedBarcode, $productInfo["name"]);
        return processKnownBarcode($productInfo, $sanitizedBarcode, $websocketEnabled, $lockGenerator, $bestBeforeInDays, $price);
    }
}


//Event types used for plugins
const EVENT_TYPE_ERROR                = -1;
const EVENT_TYPE_MODE_CHANGE          =  0;
const EVENT_TYPE_CONSUME              =  1;
const EVENT_TYPE_CONSUME_S            =  2;
const EVENT_TYPE_PURCHASE             =  3;
const EVENT_TYPE_OPEN                 =  4;
const EVENT_TYPE_INVENTORY            =  5;
const EVENT_TYPE_EXEC_CHORE           =  6;
const EVENT_TYPE_ADD_KNOWN_BARCODE    =  7;
const EVENT_TYPE_ADD_NEW_BARCODE      =  8;
const EVENT_TYPE_ADD_UNKNOWN_BARCODE  =  9;
const EVENT_TYPE_CONSUME_PRODUCT      = 10;
const EVENT_TYPE_CONSUME_S_PRODUCT    = 11;
const EVENT_TYPE_PURCHASE_PRODUCT     = 12;
const EVENT_TYPE_OPEN_PRODUCT         = 13;
const EVENT_TYPE_GET_STOCK_PRODUCT    = 14;
const EVENT_TYPE_ADD_TO_SHOPPINGLIST  = 15;
const EVENT_TYPE_ASSOCIATE_PRODUCT    = 16;
const EVENT_TYPE_ACTION_REQUIRED      = 17;
const EVENT_TYPE_CONSUME_ALL_PRODUCT  = 18;


const WS_RESULT_PRODUCT_FOUND         =  0;
const WS_RESULT_PRODUCT_LOOKED_UP     =  1;
const WS_RESULT_PRODUCT_UNKNOWN       =  2;
const WS_RESULT_MODE_CHANGE           =  4;
const WS_RESULT_ERROR                 = 'E';


//Save a log input to the database or submit websocket
function outputLog($log, $eventType, $isVerbose = false, $websocketEnabled = true, $websocketResultCode = WS_RESULT_PRODUCT_FOUND, $websocketText = null) {
    require_once __DIR__ . "/db.inc.php";
    global $LOADED_PLUGINS;
    global $db;
    $db->saveLog($log, $isVerbose);
    if ($websocketText == null) {
        $websocketText = $log;
    }
    sendWebsocketMessage($websocketText, $websocketEnabled, $websocketResultCode);
    if (in_array("EventReceiver", $LOADED_PLUGINS)) {
        pluginEventReceiver_processEvent($eventType, $log);
    }
    return $log;
}

//Execute a chore when chore barcode was submitted
function processChoreBarcode($barcode) {
    require_once __DIR__ . "/db.inc.php";
    global $db;
    $id = $db->getChoreBarcode(sanitizeString($barcode))['choreId'];
    checkIfNumeric($id);
    API::executeChore( $id);
    return sanitizeString(API::getChoresInfo($id)["name"]);
}

//If grocy does not know this barcode
function processUnknownBarcode($barcode, $websocketEnabled, &$fileLock) {
    require_once __DIR__ . "/db.inc.php";
    global $db;
    $amount = 1;
    if ($db->getTransactionState() == STATE_PURCHASE) {
        $amount = $db->getQuantityByBarcode($barcode);
    }
    if ($db->isUnknownBarcodeAlreadyStored($barcode)) {
        //Unknown barcode already in local database
        $db->addQuantitiyToUnknownBarcode($barcode, $amount);
        $output = outputLog("Unknown product already scanned. Increasing quantitiy. Barcode: " . $barcode, EVENT_TYPE_ADD_NEW_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_LOOKED_UP);
    } else {
        $productname = "N/A";
        if (is_numeric($barcode)) {
            $productname = API::lookupNameByBarcodeInOpenFoodFacts($barcode);
        }
        if ($productname != "N/A") {
            $db->insertUnrecognizedBarcode($barcode,  $amount, $productname, $db->checkNameForTags($productname));
            $output = outputLog("Unknown barcode looked up, found name: " . $productname . ". Barcode: " . $barcode, EVENT_TYPE_ADD_NEW_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_LOOKED_UP, $productname);
        } else {
            $db->insertUnrecognizedBarcode($barcode, $amount);
            $output = outputLog("Unknown barcode could not be looked up. Barcode: " . $barcode, EVENT_TYPE_ADD_UNKNOWN_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_UNKNOWN, $barcode);
        }
    }
    $fileLock->removeLock();
    return $output;
}


//Convert state to string for websocket server
function stateToString($state) {
    $allowedModes = array(
        STATE_CONSUME => "Consume",
        STATE_CONSUME_SPOILED => "Consume (spoiled)",
        STATE_PURCHASE => "Purchase",
        STATE_OPEN => "Open",
        STATE_GETSTOCK => "Inventory",
        STATE_ADD_SL => "Add to shoppinglist"
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
        outputLog("Weight unchanged for: " . $product["name"], EVENT_TYPE_ACTION_REQUIRED, true, false);
        return true;
    }
    if ($newWeight < $product["tareWeight"]) {
        outputLog("Entered weight for " . $product["name"] . " is below tare weight (" . $product["tareWeight"] . ")", EVENT_TYPE_ADD_UNKNOWN_BARCODE, false, false);
        return false;
    }

    if ($product["stockAmount"] > ($newWeight - $product["tareWeight"])) {
        API::consumeProduct($product["id"], $newWeight);
    } else {
        API::purchaseProduct($product["id"], $newWeight);
    }
    outputLog("Weight set to ".$newWeight." for: " . $product["name"], EVENT_TYPE_ACTION_REQUIRED, true, false);
    return true;
}

//Change mode if was supplied by GET parameter
function processModeChangeGetParameter($modeParameter) {
    require_once __DIR__ . "/db.inc.php";
    global $db;
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
    require_once __DIR__ . "/db.inc.php";
    global $db;
    $productInfo = API::getProductByBardcode($barcode);
    if ($productInfo != null) {
        $db->updateSavedBarcodeMatch($barcode, $productInfo["id"]);
    }
}

//We are using the old inventory API until the next Grocy version is officially released
const USE_NEW_INVENTORY_API = false;

    // Process a barcode that Grocy already knows
function processKnownBarcode($productInfo, $barcode, $websocketEnabled, &$fileLock, $bestBeforeInDays, $price) {
    require_once __DIR__ . "/db.inc.php";
    global $BBCONFIG;
    global $db;

    $output = "Undefined";

    if ($productInfo["isTare"]) {
        if (!$db->isUnknownBarcodeAlreadyStored($barcode)) 
            $db->insertActionRequiredBarcode($barcode);
        $fileLock->removeLock();
        return outputLog("Action required: Enter weight for " . $productInfo["name"] . ". Barcode: " . $barcode, EVENT_TYPE_ACTION_REQUIRED, false, $websocketEnabled, WS_RESULT_PRODUCT_FOUND, "Action required: Enter weight for " . $productInfo["name"]);
    }


    $state = $db->getTransactionState();
    
    switch ($state) {
        case STATE_CONSUME:
            $amountToConsume = getQuantitiyForBarcode($barcode, true, $productInfo);

            if ($productInfo["stockAmount"] > 0) { 
                if ($productInfo["stockAmount"] < $amountToConsume)
                    $amountToConsume = $productInfo["stockAmount"];

                API::consumeProduct($productInfo["id"], $amountToConsume, false);
                $fileLock->removeLock();
                return outputLog("Product found. Consuming ".$amountToConsume." " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode, EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_FOUND, "Consuming ".$amountToConsume." " . $productInfo["unit"] . " of " . $productInfo["name"]);
            } else {
                return outputLog("Product found. None in stock, not consuming: " . $productInfo["name"] . ". Barcode: " . $barcode, EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_FOUND, "Product found. None in stock, not consuming: " . $productInfo["name"]);
            }
        case STATE_CONSUME_ALL:
            $amountToConsume = $productInfo["stockAmount"];
            if ($productInfo["stockAmount"] > 0) { 
                API::consumeProduct($productInfo["id"], $amountToConsume, false);
                $fileLock->removeLock();
                $output = outputLog("Product found. Consuming ".$amountToConsume." " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode, EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_FOUND, "Consuming ".$amountToConsume." " . $productInfo["unit"] . " of " . $productInfo["name"]);
            } else {
                $output = outputLog("Product found. None in stock, not consuming: " . $productInfo["name"] . ". Barcode: " . $barcode, EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_FOUND, "Product found. None in stock, not consuming: " . $productInfo["name"]);
            }
            if ($BBCONFIG["REVERT_SINGLE"]) {
                $db->saveLog("Reverting back to Consume", true);
                $db->setTransactionState(STATE_CONSUME);
            }
            return $output;
        case STATE_CONSUME_SPOILED:
            API::consumeProduct($productInfo["id"], 1, true);
            $fileLock->removeLock();
            $output = outputLog("Product found. Consuming 1 spoiled " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode, EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_FOUND, "Consuming 1 spoiled " . $productInfo["unit"] . " of " . $productInfo["name"]);
            if ($BBCONFIG["REVERT_SINGLE"]) {
                $db->saveLog("Reverting back to Consume", true);
                $db->setTransactionState(STATE_CONSUME);
            }
            return $output;
        case STATE_PURCHASE:
            $amount        = getQuantitiyForBarcode($barcode, false, $productInfo);
            $additionalLog = "";
            $isBestBeforeSet = API::purchaseProduct($productInfo["id"], $amount, $bestBeforeInDays, $price, $fileLock, $productInfo["defaultBestBefore"]);
            if (!$isBestBeforeSet && $bestBeforeInDays == null) {
                $additionalLog = " [WARNING]: No default best before date set!";
            }
            return outputLog("Product found. Adding  $amount " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode . $additionalLog, EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_FOUND, "Adding $amount " . $productInfo["unit"] . " of " . $productInfo["name"] . $additionalLog);
        case STATE_OPEN:
            if ($productInfo["stockAmount"] > 0) { 
                API::openProduct($productInfo["id"]);
                $output = outputLog("Product found. Opening 1 " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode, EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_FOUND, "Opening 1 " . $productInfo["unit"] . " of " . $productInfo["name"]);
            } else {
                $output = outputLog("Product found. None in stock, not opening: " . $productInfo["name"] . ". Barcode: " . $barcode, EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_FOUND, "Product found. None in stock, not opening: " . $productInfo["name"]);
            }
            $fileLock->removeLock();
            if ($BBCONFIG["REVERT_SINGLE"]) {
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
                        $log = $log . "\nLocation ". $location["location_name"] . ": ".$location["amount"]. " " . $productInfo["unit"];
                    }
                } 
            }
            return outputLog($log, EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled);
        case STATE_ADD_SL:
            API::addToShoppinglist($productInfo["id"], 1);
            $fileLock->removeLock();
            return outputLog("Added to shopping list: 1 " . $productInfo["unit"] . " of " . $productInfo["name"], EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled);
    }
}

function getQuantitiyForBarcode($barcode, $isConsume, $productInfo) {
    global $BBCONFIG;
    global $db;

    if ($isConsume && !$BBCONFIG["CONSUME_SAVED_QUANTITY"])
        return 1;
    if ($BBCONFIG["USE_GROCY_QU_FACTOR"])
        return intval($productInfo["quFactor"]);
    return $amount = $db->getQuantityByBarcode($barcode);
}


//Function for generating the <select> elements in the web ui
function printSelections($selected, $productinfo) {
    
    $selections = array();
    foreach ($productinfo as $product) {
        $selections[$product["id"]] = $product["name"];
    }
    natcasesort($selections);
    
    $optionscontent = "<option value=\"0\" >= None =</option>";
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
    require_once __DIR__ . "/db.inc.php";
    global $db;
    if ($words == "N/A") {
        return "";
    }
    $selections  = "";
    $cleanWords = cleanNameForTagLookup($words);
    $i           = 0;
    foreach ($cleanWords as $str) {
    $tagWord = trim($str);
        if (strlen($tagWord) > 0 && $db->tagNotUsedYet($tagWord)) {
            $selections = $selections . '<label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="checkbox-' . $id . '_' . $i . '">
  <input type="checkbox"  value="' . $tagWord . '" name="tags[' . $id . '][' . $i . ']" id="checkbox-' . $id . '_' . $i . '" class="mdl-checkbox__input">
  <span class="mdl-checkbox__label">' . $tagWord . '</span>
</label>';
            $i++;
        }
    }
    return $selections;
}

function cleanNameForTagLookup($input) {
    $ignoreChars = array(",", ".", "-", ":","(",")");
    $cleanWords  = str_replace($ignoreChars, " ", $input);
    return explode(' ', $cleanWords);
}

//If a quantity barcode was scanned, add the quantitiy and process further
function changeQuantityAfterScan($amount) {
    require_once __DIR__ . "/db.inc.php";
    global $BBCONFIG;
    global $db;
    $barcode = sanitizeString($BBCONFIG["LAST_BARCODE"]);
    if ($BBCONFIG["LAST_PRODUCT"] != null) {
        $db->addUpdateQuantitiy($barcode, $amount, $BBCONFIG["LAST_PRODUCT"]);
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
    require_once __DIR__ . "/db.inc.php";
    global $db;
    $tags       = $db->getStoredTags();
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
function sortTags($a,$b) {
          return $a['item']>$b['item'];
     }


//Sorts the chores by name
function sortChores($a,$b) {
          return $a['name']>$b['name'];
     }


//Merges chores with chore info
function getAllChores() {
    require_once __DIR__ . "/db.inc.php";
    global $db;
    $chores = API::getChoresInfo();
    $barcodes = $db->getStoredChoreBarcodes();
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
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

function getApiUrl($removeAfter) {
    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

    $url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    return $requestedUrl = trim(substr($url, 0, strpos($url, $removeAfter)))."api/";
}

function showErrorNotWritable($error="DB Error") {
    die($error . ": Database file cannot be created, as folder or database file is not writable. Please check your permissions.<br>
             Have a look at this link to find out how to do this:
             <a href='https://github.com/olab/Open-Labyrinth/wiki/How-do-I-make-files-and-folders-writable-for-the-web-server%3F'>" . "How do I make files and folders writable for the web server?</a>");
}

?>
