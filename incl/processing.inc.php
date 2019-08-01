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


// Function that is called when a barcode is passed on
function processNewBarcode($barcode, $websocketEnabled = true) {
    $isProcessed = false;
    if ($barcode == BARCODE_SET_CONSUME) {
        setTransactionState(STATE_CONSUME);
        saveLog("Set state to consume", true);
        sendWebsocketMessage("Set state to consume", $websocketEnabled);
        $isProcessed = true;
    }
    if ($barcode == BARCODE_SET_CONSUME_SPOILED) {
        setTransactionState(STATE_CONSUME_SPOILED);
        saveLog("Set state to consume (spoiled)", true);
        sendWebsocketMessage("Set state to consume (spoiled)", $websocketEnabled);
        $isProcessed = true;
    }
    if ($barcode == BARCODE_SET_PURCHASE) {
        setTransactionState(STATE_CONSUME_PURCHASE);
        saveLog("Set state to purchase", true);
        sendWebsocketMessage("Set state to purchase", $websocketEnabled);
        $isProcessed = true;
    }
    if ($barcode == BARCODE_SET_OPEN) {
        setTransactionState(STATE_CONSUME_OPEN);
        saveLog("Set state to open", true);
        sendWebsocketMessage("Set state to open", $websocketEnabled);
        $isProcessed = true;
    }
    
    if (trim($barcode) == "") {
        saveLog("Invalid barcode found", true);
        sendWebsocketMessage("Invalid barcode found", $websocketEnabled, 2);
        $isProcessed = true;
    }

    if (isChoreBarcode($barcode)==true) {
        $choreText = processChoreBarcode($barcode);
         saveLog("Executed chore: ".$choreText, true);
        sendWebsocketMessage("Executed chore: ".$choreText, $websocketEnabled);
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


function processChoreBarcode($barcode) {
   $id = getChoreBarcode($barcode)['choreId'];
   checkIfNumeric($id);
   executeChore( $id);
   return sanitizeString(getChoresInfo($id)["name"]);
};

//If grocy does not know this barcode #TODO refactor into db.php
function processUnknownBarcode($barcode, $websocketEnabled) {
    global $db;
    $count = $db->querySingle("SELECT COUNT(*) as count FROM Barcodes WHERE barcode='$barcode'");
    if ($count != 0) {
        //Unknown barcode already in local database
        saveLog("Unknown product already scanned. Increasing quantitiy. Barcode: " . $barcode);
        sendWebsocketMessage("Unknown product already scanned. Increasing quantitiy", $websocketEnabled, 1);
        $db->exec("UPDATE Barcodes SET amount = amount + 1 WHERE barcode = '$barcode'");
    } else {
        $productname = lookupNameByBarcode($barcode);
        if ($productname != "N/A") {
            saveLog("Unknown barcode looked up, found name: " . $productname . ". Barcode: " . $barcode);
            sendWebsocketMessage($productname, $websocketEnabled, 1);
            $db->exec("INSERT INTO Barcodes(barcode, name, amount, possibleMatch) VALUES('$barcode', '$productname', 1," . checkNameForTags($productname) . ")");
        } else {
            saveLog("Unknown barcode could not be looked up. Barcode: " . $barcode);
            sendWebsocketMessage($barcode, $websocketEnabled, 2);
            $db->exec("INSERT INTO Barcodes(barcode, name, amount) VALUES('$barcode', 'N/A', 1)");
        }
        
    }
}

//Process a barcode that Grocy already knows
function processKnownBarcode($productInfo, $barcode, $websocketEnabled) {
    $state = getTransactionState();
    
    switch ($state) {
        case STATE_CONSUME:
            consumeProduct($productInfo["id"], 1, false);
            saveLog("Product found. Consuming 1 " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode);
            sendWebsocketMessage("Consuming 1 " . $productInfo["unit"] . " of " . $productInfo["name"], $websocketEnabled);
            break;
        case STATE_CONSUME_SPOILED:
            consumeProduct($productInfo["id"], 1, true);
            saveLog("Product found. Consuming 1 spoiled " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode);
            sendWebsocketMessage("Consuming 1 spoiled " . $productInfo["unit"] . " of " . $productInfo["name"], $websocketEnabled);
            if (REVERT_TO_CONSUME) {
                saveLog("Reverting back to Consume", true);
                setTransactionState(STATE_CONSUME);
            }
            break;
        case STATE_CONSUME_PURCHASE:
            saveLog("Product found. Adding 1 " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode);
            sendWebsocketMessage("Adding 1 " . $productInfo["unit"] . " of " . $productInfo["name"], $websocketEnabled);
            purchaseProduct($productInfo["id"], 1);
            break;
        case STATE_CONSUME_OPEN:
            saveLog("Product found. Opening 1 " . $productInfo["unit"] . " of " . $productInfo["name"] . ". Barcode: " . $barcode);
            sendWebsocketMessage("Opening 1 " . $productInfo["unit"] . " of " . $productInfo["name"], $websocketEnabled);
            openProduct($productInfo["id"]);
            if (REVERT_TO_CONSUME) {
                saveLog("Reverting back to Consume", true);
                setTransactionState(STATE_CONSUME);
            }
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



function saveSettings() {
    global $BBCONFIG;
    foreach ($BBCONFIG as $key => $value) {
        if (isset($_POST[$key])) {
            if ($_POST[$key] != $value) {
                updateConfig($key, $_POST[$key]);
            }
        } else {
            if (isset($_POST[$key . "_hidden"]) && $_POST[$key . "_hidden"] != $value) {
                updateConfig($key, $_POST[$key]);
            }
        }
    }
}


//Stop script if default API details still set
function testIfApiIsSet() {
    if (API_URL == 'https://your.grocy.site/api/') {
        die("Please set the API details in config.php");
    }
    if (API_URL != (rtrim(API_URL, '/') . '/')) {
        die("API_URL in config.php must contain a trailing slash");
    }
}

?>
