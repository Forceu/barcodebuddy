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
 * Helper file for processing functions
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */

require_once __DIR__ . "/db.inc.php";

// Function that is called when a barcode is passed on
function processNewBarcode($barcodeInput, $websocketEnabled = true) {
    global $db;
    global $BBCONFIG;
    
    $barcode     = strtoupper($barcodeInput);
    $isProcessed = false;
    if ($barcode == $BBCONFIG["BARCODE_C"]) {
        $db->setTransactionState(STATE_CONSUME);
        outputLog("Set state to Consume", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
        $isProcessed = true;
    }
    if ($barcode == $BBCONFIG["BARCODE_CS"]) {
        $db->setTransactionState(STATE_CONSUME_SPOILED);
        outputLog("Set state to Consume (spoiled)", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
        $isProcessed = true;
    }
    if ($barcode == $BBCONFIG["BARCODE_P"]) {
        $db->setTransactionState(STATE_PURCHASE);
        outputLog("Set state to Purchase", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
        $isProcessed = true;
    }
    if ($barcode == $BBCONFIG["BARCODE_O"]) {
        $db->setTransactionState(STATE_OPEN);
        outputLog("Set state to Open", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
        $isProcessed = true;
    }
    if ($barcode == $BBCONFIG["BARCODE_GS"]) {
        $db->setTransactionState(STATE_GETSTOCK);
        outputLog("Set state to Inventory", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
        $isProcessed = true;
    }
    if ($barcode == $BBCONFIG["BARCODE_AS"]) {
        $db->setTransactionState(STATE_ADD_SL);
        outputLog("Set state to Shopping list", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
        $isProcessed = true;
    }
    if (stringStartsWith($barcode, $BBCONFIG["BARCODE_Q"])) {
        $quantitiy = str_replace($BBCONFIG["BARCODE_Q"], "", $barcode);
        checkIfNumeric($quantitiy);
	if ($BBCONFIG["LAST_PRODUCT"] != null) {
	    $lastBarcode = $BBCONFIG["LAST_BARCODE"] . " (" . $BBCONFIG["LAST_PRODUCT"] . ")";
	} else {
	    $lastBarcode = $BBCONFIG["LAST_BARCODE"];
	}
        outputLog("Set quantitiy to $quantitiy for barcode $lastBarcode", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
        changeQuantityAfterScan($quantitiy);
        $isProcessed = true;
    }
    if (trim($barcode) == "") {
        outputLog("Invalid barcode found", EVENT_TYPE_ERROR, true, $websocketEnabled, WS_RESULT_PRODUCT_UNKNOWN);
        $isProcessed = true;
    }
    
    if ($db->isChoreBarcode($barcode)) {
        $choreText = processChoreBarcode($barcode);
        outputLog("Executed chore: " . $choreText, EVENT_TYPE_EXEC_CHORE, true, $websocketEnabled);
        $isProcessed = true;
    }
    
    if (!$isProcessed) {
        $sanitizedBarcode = sanitizeString($barcode);
        $productInfo = API::getProductByBardcode($sanitizedBarcode);
        if ($productInfo == null) {
            $db->saveLastBarcode($sanitizedBarcode);
            processUnknownBarcode($sanitizedBarcode, $websocketEnabled);
        } else {
            $db->saveLastBarcode($sanitizedBarcode, $productInfo["name"]);
            processKnownBarcode($productInfo, $sanitizedBarcode, $websocketEnabled);
        }
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


const WS_RESULT_PRODUCT_FOUND         =  0;
const WS_RESULT_PRODUCT_LOOKED_UP     =  1;
const WS_RESULT_PRODUCT_UNKNOWN       =  2;
const WS_RESULT_MODE_CHANGE           =  4;
const WS_RESULT_ERROR                 = 'E';


//Save a log input to the database or submit websocket
function outputLog($log, $eventType, $isVerbose = false, $websocketEnabled = true, $websocketResultCode = WS_RESULT_PRODUCT_FOUND, $websocketText = null) {
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
}

//Execute a chore when chore barcode was submitted
function processChoreBarcode($barcode) {
   global $db;
   $id = $db->getChoreBarcode(sanitizeString($barcode))['choreId'];
   checkIfNumeric($id);
   API::executeChore( $id);
   return sanitizeString(API::getChoresInfo($id)["name"]);
}

//If grocy does not know this barcode
function processUnknownBarcode($barcode, $websocketEnabled) {
    global $db;
    $amount = 1;
    if ($db->getTransactionState() == STATE_PURCHASE) {
        $amount = $db->getQuantityByBarcode($barcode);
    }
    if ($db->isUnknownBarcodeAlreadyStored($barcode)) {
        //Unknown barcode already in local database
        $db->addQuantitiyToUnknownBarcode($barcode, $amount);
        outputLog("Unknown product already scanned. Increasing quantitiy. Barcode: " . $barcode, EVENT_TYPE_ADD_NEW_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_LOOKED_UP);
    } else {
        $productname = "N/A";
        if (is_numeric($barcode)) {
            $productname = API::lookupNameByBarcodeInOpenFoodFacts($barcode);
        }
        if ($productname != "N/A") {
            $db->insertUnrecognizedBarcode($barcode,  $amount, $productname, $db->checkNameForTags($productname));
            outputLog("Unknown barcode looked up, found name: " . $productname . ". Barcode: " . $barcode, EVENT_TYPE_ADD_NEW_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_LOOKED_UP, $productname);
        } else {
            $db->insertUnrecognizedBarcode($barcode, $amount);
            outputLog("Unknown barcode could not be looked up. Barcode: " . $barcode, EVENT_TYPE_ADD_UNKNOWN_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_UNKNOWN, $barcode);
        }
        
    }
}


//Convert state to string for websocket server
function stateToString($state) {
	$allowedModes = array(STATE_CONSUME=>"Consume",STATE_CONSUME_SPOILED=> "Consume (spoiled)",STATE_PURCHASE=> "Purchase",STATE_OPEN=> "Open",STATE_GETSTOCK=> "Inventory", STATE_ADD_SL=> "Add to shoppinglist");
	return $allowedModes[$state];
}


//Change mode if was supplied by GET parameter
function processModeChangeGetParameter($modeParameter) {
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
    global $db;
    $productInfo = API::getProductByBardcode($barcode);
    if ($productInfo != null) {
        $db->updateSavedBarcodeMatch($barcode, $productInfo["id"]);
    }
}

//We are using the old inventory API until the next Grocy version is officially released
const USE_NEW_INVENTORY_API = false;

    // Process a barcode that Grocy already knows
function processKnownBarcode($productInfo, $barcode, $websocketEnabled) {
    global $BBCONFIG;
    global $db;
    $state = $db->getTransactionState();
    
    switch ($state) {
        case STATE_CONSUME:
            if ($productInfo["stockAmount"] > 0) { 
                API::consumeProduct($productInfo["id"], 1, false);
                outputLog("Product found. Consuming 1 " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode, EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_FOUND, "Consuming 1 " . $productInfo["unit"] . " of " . $productInfo["name"]);
            } else {
                outputLog("Product found. None in stock, not consuming: " . $productInfo["name"] . ". Barcode: " . $barcode, EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_FOUND, "Product found. None in stock, not consuming: " . $productInfo["name"]);
            }
            break;
        case STATE_CONSUME_SPOILED:
            API::consumeProduct($productInfo["id"], 1, true);
            outputLog("Product found. Consuming 1 spoiled " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode, EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_FOUND, "Consuming 1 spoiled " . $productInfo["unit"] . " of " . $productInfo["name"]);
            if ($BBCONFIG["REVERT_SINGLE"]) {
                $db->saveLog("Reverting back to Consume", true);
                $db->setTransactionState(STATE_CONSUME);
            }
            break;
        case STATE_PURCHASE:
            $amount        = $db->getQuantityByBarcode($barcode);
            $additionalLog = "";
            if (!API::purchaseProduct($productInfo["id"], $amount)) {
                $additionalLog = " [WARNING]: No default best before date set!";
            }
            outputLog("Product found. Adding  $amount " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode . $additionalLog, EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_FOUND, "Adding $amount " . $productInfo["unit"] . " of " . $productInfo["name"] . $additionalLog);
            break;
        case STATE_OPEN:
                API::openProduct($productInfo["id"]);
            if ($productInfo["stockAmount"] > 0) { 
                outputLog("Product found. Opening 1 " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode, EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_FOUND, "Opening 1 " . $productInfo["unit"] . " of " . $productInfo["name"]);
            } else {
                outputLog("Product found. None in stock, not opening: " . $productInfo["name"] . ". Barcode: " . $barcode, EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled, WS_RESULT_PRODUCT_FOUND, "Product found. None in stock, not opening: " . $productInfo["name"]);
            }
            if ($BBCONFIG["REVERT_SINGLE"]) {
                $db->saveLog("Reverting back to Consume", true);
                $db->setTransactionState(STATE_CONSUME);
            }
            break;
        case STATE_GETSTOCK:
            $output = "Currently in stock: " . $productInfo["stockAmount"] . " " . $productInfo["unit"] . " of " . $productInfo["name"];
            if (USE_NEW_INVENTORY_API) {
                if ($productInfo["stockAmount"] > 0) {
                    $locationInfo = API::getProductLocations($productInfo["id"]);
                    foreach ($locationInfo as $location) {
                        $output = $output . "\nLocation ". $location["location_name"] . ": ".$location["amount"]. " " . $productInfo["unit"];
                    }
                } 
            }
            outputLog($output, EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled);
            break;
        case STATE_ADD_SL:
            API::addToShoppinglist($productInfo["id"], 1);
            outputLog("Added to shopping list: 1 " . $productInfo["unit"] . " of " . $productInfo["name"], EVENT_TYPE_ADD_KNOWN_BARCODE, false, $websocketEnabled);
            break;
    }
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
    global $db;
    if ($words == "N/A") {
        return "";
    }
    $selections  = "";
    $ignoreChars = array(",", ".", "-", ":","(",")");
    $cleanWords  = str_replace($ignoreChars, " ", $words);
    $ary         = explode(' ', $cleanWords);
    $i           = 0;
    foreach ($ary as $str) {
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

//If a quantity barcode was scanned, add the quantitiy and process further
function changeQuantityAfterScan($amount) {
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

function generateRandomString($length = 60) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}


?>
