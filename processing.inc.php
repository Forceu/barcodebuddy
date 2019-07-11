<?php


/**
 * Barcode Buddy for Grocy
 *
 * Long description for file (if any)...
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
function processNewBarcode($barcode) {
    
    if ($barcode == BARCODE_SET_CONSUME) {
        setTransactionState(STATE_CONSUME);
        die;
    }
    if ($barcode == BARCODE_SET_CONSUME_SPOILED) {
        setTransactionState(STATE_CONSUME_SPOILED);
        die;
    }
    if ($barcode == BARCODE_SET_PURCHASE) {
        setTransactionState(STATE_CONSUME_PURCHASE);
        die;
    }
    if ($barcode == BARCODE_SET_OPEN) {
        setTransactionState(STATE_CONSUME_OPEN);
        die;
    }
    
    if (trim($barcode) == "") {
        die;
    }

    $foundId = getProductByBardcode($barcode);
    if ($foundId == null) {
        processUnknownBarcode($barcode);
    } else {
        processKnownBarcode($foundId);
    }
}

//If grocy does not know this barcode
function processUnknownBarcode($barcode) {
    global $db;
    $count = $db->querySingle("SELECT COUNT(*) as count FROM Barcodes WHERE barcode='$barcode'");
    if ($count != 0) {
        //Unknown barcode already in local database
        $db->exec("UPDATE Barcodes SET amount = amount + 1 WHERE barcode = '$barcode'");
    } else {
        $productname = lookupNameByBarcode($barcode);
        if ($productname != "N/A") {
            $db->exec("INSERT INTO Barcodes(barcode, name, amount, possibleMatch) VALUES('$barcode', '$productname', 1," . checkNameForTags($productname) . ")");
        } else {
            $db->exec("INSERT INTO Barcodes(barcode, name, amount) VALUES('$barcode', 'N/A', 1)");
        }
        
    }
}

//Process a barcode that Grocy already knows
function processKnownBarcode($id) {
    $state = getTransactionState();
    
    switch ($state) {
        case STATE_CONSUME:
            consumeProduct($id, 1, false);
            break;
        case STATE_CONSUME_SPOILED:
            consumeProduct($id, 1, true);
            if (REVERT_TO_CONSUME) {
                setTransactionState(STATE_CONSUME);
            }
            break;
        case STATE_CONSUME_PURCHASE:
            purchaseProduct($id, 1);
            break;
        case STATE_CONSUME_OPEN:
            if (REVERT_TO_CONSUME) {
                setTransactionState(STATE_CONSUME);
            }
            openProduct($id);
            break;
    }
}



//Function for generating the <select> elements in the web ui
function printSelections($selected) {
    
    $selections = array();
    foreach (getProductInfo() as $product) {
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
        die("Illegal input");
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
        $count = $db->querySingle("SELECT COUNT(*) as count FROM Tags WHERE tag='" . $str . "'");
        if ($count == 0) {
            $selections = $selections . "<input type=\"checkbox\" name=\"tags_" . $id . "_" . $i . "\" value=\"$str\"> $str";
            $i++;
        }
    }
    return $selections;
}

?>
