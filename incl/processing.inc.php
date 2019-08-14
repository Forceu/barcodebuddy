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
    global $BBCONFIG;
    
    $barcode     = strtoupper($barcodeInput);
    $isProcessed = false;
    if ($barcode == $BBCONFIG["BARCODE_C"]) {
        setTransactionState(STATE_CONSUME);
        outputLog("Set state to Consume", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
        $isProcessed = true;
    }
    if ($barcode == $BBCONFIG["BARCODE_CS"]) {
        setTransactionState(STATE_CONSUME_SPOILED);
        outputLog("Set state to Consume (spoiled)", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
        $isProcessed = true;
    }
    if ($barcode == $BBCONFIG["BARCODE_P"]) {
        setTransactionState(STATE_PURCHASE);
        outputLog("Set state to Purchase", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
        $isProcessed = true;
    }
    if ($barcode == $BBCONFIG["BARCODE_O"]) {
        setTransactionState(STATE_OPEN);
        outputLog("Set state to Open", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
        $isProcessed = true;
    }
    
    if ($barcode == $BBCONFIG["BARCODE_GS"]) {
        setTransactionState(STATE_GETSTOCK);
        outputLog("Set state to Inventory", EVENT_TYPE_MODE_CHANGE, true, $websocketEnabled);
        $isProcessed = true;
    }
    
    if (trim($barcode) == "") {
        outputLog("Invalid barcode found", EVENT_TYPE_ERROR, true, $websocketEnabled, 2);
        $isProcessed = true;
    }
    
    if (isChoreBarcode($barcode) == true) {
        $choreText = processChoreBarcode($barcode);
        outputLog("Executed chore: " . $choreText, EVENT_TYPE_EXEC_CHORE, true, $websocketEnabled);
        $isProcessed = true;
    }
    
    if (!$isProcessed) {
        $productInfo = getProductByBardcode($barcode);
        if ($productInfo == null) {
            processUnknownBarcode($barcode, $websocketEnabled);
        } else {
            processKnownBarcode($productInfo, $barcode, $websocketEnabled);
        }
    }
}

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

function outputLog($log, $eventType, $isVerbose = false, $websocketEnabled = true, $websocketResultCode = "0", $websocketText = null) {
    global $LOADED_PLUGINS;
    saveLog($log, $isVerbose);
    if ($websocketText == null) {
        $websocketText = $log;
    }
    sendWebsocketMessage($websocketText, $websocketEnabled, $websocketResultCode);
    if (in_array("EventReceiver", $LOADED_PLUGINS)) {
        pluginEventReceiver_processEvent($eventType, $log);
    }
}

function processChoreBarcode($barcode) {
   $id = getChoreBarcode($barcode)['choreId'];
   checkIfNumeric($id);
   executeChore( $id);
   return sanitizeString(getChoresInfo($id)["name"]);
}

//If grocy does not know this barcode
function processUnknownBarcode($barcode, $websocketEnabled) {
    global $db;
    if (isUnknownBarcodeAlreadyStored($barcode)) {
        //Unknown barcode already in local database
        outputLog("Unknown product already scanned. Increasing quantitiy. Barcode: " . $barcode, EVENT_TYPE_ADD_NEW_BARCODE, false, $websocketEnabled, 1);
        addQuantitiyToUnknownBarcode($barcode, 1);
    } else {
        $productname = "N/A";
        if (is_numeric($barcode)) {
            $productname = lookupNameByBarcode($barcode);
        }
        if ($productname != "N/A") {
            outputLog("Unknown barcode looked up, found name: " . $productname . ". Barcode: " . $barcode, EVENT_TYPE_ADD_NEW_BARCODE, false, $websocketEnabled, 1, $productname);
            insertUnrecognizedBarcode($barcode, $productname, 1, checkNameForTags($productname));
        } else {
            outputLog("Unknown barcode could not be looked up. Barcode: " . $barcode, EVENT_TYPE_ADD_UNKNOWN_BARCODE, false, $websocketEnabled, 2, $barcode);
            insertUnrecognizedBarcode($barcode);
        }
        
    }
}


function processModeChangeGetParameter($modeParameter) {
    switch (trim($modeParameter)) {
        case "consume":
	    setTransactionState(STATE_CONSUME);
            break;
        case "consume_s":
	    setTransactionState(STATE_CONSUME_SPOILED);
            break;
        case "purchase":
	    setTransactionState(STATE_PURCHASE);
            break;
        case "open":
	    setTransactionState(STATE_OPEN);
            break;
        case "inventory":
	    setTransactionState(STATE_GETSTOCK);
            break;
    }
}


//This will be called when a new grocy product is created from BB and the grocy tab is closed
function processRefreshedBarcode($barcode) {
    $productInfo = getProductByBardcode($barcode);
    if ($productInfo != null) {
        updateSavedBarcodeMatch($barcode, $productInfo["id"]);
    }
}

//Process a barcode that Grocy already knows
function processKnownBarcode($productInfo, $barcode, $websocketEnabled) {
    global $BBCONFIG;
    $state = getTransactionState();
    
    switch ($state) {
        case STATE_CONSUME:
            consumeProduct($productInfo["id"], 1, false);
            outputLog("Product found. Consuming 1 " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode, EVENT_TYPE_ADD_UNKNOWN_BARCODE, false, $websocketEnabled, 0, "Consuming 1 " . $productInfo["unit"] . " of " . $productInfo["name"]);
            break;
        case STATE_CONSUME_SPOILED:
            consumeProduct($productInfo["id"], 1, true);
            outputLog("Product found. Consuming 1 spoiled " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode, EVENT_TYPE_ADD_UNKNOWN_BARCODE, false, $websocketEnabled, 0, "Consuming 1 spoiled " . $productInfo["unit"] . " of " . $productInfo["name"]);
            if ($BBCONFIG["REVERT_SINGLE"]) {
                saveLog("Reverting back to Consume", true);
                setTransactionState(STATE_CONSUME);
            }
            break;
        case STATE_PURCHASE:
 	    $additionalLog = "";
            if (!purchaseProduct($productInfo["id"], 1)) {
		$additionalLog = " [WARNING]: No default best before date set!";
		}
            outputLog("Product found. Adding 1 " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode . $additionalLog, EVENT_TYPE_ADD_UNKNOWN_BARCODE, false, $websocketEnabled, 0, "Adding 1 " . $productInfo["unit"] . " of " . $productInfo["name"] . $additionalLog);
            break;
        case STATE_OPEN:
            outputLog("Product found. Opening 1 " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode, EVENT_TYPE_ADD_UNKNOWN_BARCODE, false, $websocketEnabled, 0, "Opening 1 " . $productInfo["unit"] . " of " . $productInfo["name"]);
            openProduct($productInfo["id"]);
            if ($BBCONFIG["REVERT_SINGLE"]) {
                saveLog("Reverting back to Consume", true);
                setTransactionState(STATE_CONSUME);
            }
            break;
        case STATE_GETSTOCK:
            outputLog("Currently in stock: ".$productInfo["stockAmount"]. " " . $productInfo["unit"] . " of " . $productInfo["name"], EVENT_TYPE_ADD_UNKNOWN_BARCODE, false, $websocketEnabled, 0);
            break;
    }
}



//Function for generating the <select> elements in the web ui
function printSelections($selected, $productinfo) {
    
    $selections = array();
    foreach ($productinfo as $product) {
        $selections[$product["id"]] = $product["name"];
    }
    asort($selections);
    
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

function sanitizeString($input, $strongFilter = false) {
    if ($strongFilter) {
        return filter_var($input, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
    } else {
        return filter_var($input, FILTER_SANITIZE_STRING);
    }
}

function checkIfNumeric($input) {
    if (!is_numeric($input)) {
        die("Illegal input! $input needs to be a number");
    }
}

//Generate checkboxes for web ui
function explodeWords($words, $id) {
    global $db;
    $selections = "";
    $ary        = explode(' ', $words);
    $i          = 0;
    if ($words == "N/A") {
        return "";
    }
    foreach ($ary as $str) {
        $sanitizedWord = str_replace(array( '(', ')' ), '', $str);
        $count = $db->querySingle("SELECT COUNT(*) as count FROM Tags WHERE tag='" . $sanitizedWord . "'");
        if ($count == 0) {
            $selections = $selections . '<label class="mdl-checkbox mdl-js-checkbox mdl-js-ripple-effect" for="checkbox-' . $id . '_' . $i . '">
  <input type="checkbox"  value="' . $sanitizedWord . '" name="tags[' . $id . '][' . $i . ']" id="checkbox-' . $id . '_' . $i . '" class="mdl-checkbox__input">
  <span class="mdl-checkbox__label">' . $sanitizedWord . '</span>
</label>';
            $i++;
        }
    }
    return $selections;
}



function getAllTags() {
    $tags       = getStoredTags();
    $products   = getProductInfo();
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

function sortTags($a,$b) {
          return $a['item']>$b['item'];
     }


function sortChores($a,$b) {
          return $a['name']>$b['name'];
     }


function getAllChores() {
    $chores     = getChoresInfo();
    $barcodes   = getStoredChoreBarcodes();
    $returnChores = array();
    
    foreach ($chores as $chore) {
        $chore["barcode"]=null;
        foreach ($barcodes as $barcode) {
            if ($chore["id"] == $barcode["choreId"]) {
                $chore["barcode"] =$barcode["barcode"];
		break;
            }
        }
                array_push($returnChores, $chore);
    }
    usort($returnChores, "sortChores");
    return $returnChores;
}

function stringStartsWith ($string, $startString) { 
    $len = strlen($startString); 
    return (substr($string, 0, $len) === $startString); 
} 


function saveSettings() {
    global $BBCONFIG;
    foreach ($BBCONFIG as $key => $value) {
        if (isset($_POST[$key])) {
            if ($_POST[$key] != $value) {
                if (stringStartsWith($key,"BARCODE_")) {
                    updateConfig($key, strtoupper($_POST[$key]));
                } else {
		    updateConfig($key, $_POST[$key]);
	        }
            }
        } else {
            if (isset($_POST[$key . "_hidden"]) && $_POST[$key . "_hidden"] != $value) {
                updateConfig($key, $_POST[$key . "_hidden"]);
            }
        }
    }
}

?>
