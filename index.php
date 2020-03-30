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
 * Index file that receives barcodes and displays web UI
 *
 * Make sure to modify API details. This script requires php-sqlite3 and php-curl
 * 
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 *
 */



require_once __DIR__ . "/incl/config.php";
require_once __DIR__ . "/incl/api.inc.php";
require_once __DIR__ . "/incl/db.inc.php";
require_once __DIR__ . "/incl/internalChecking.inc.php";
require_once __DIR__ . "/incl/processing.inc.php";
require_once __DIR__ . "/incl/websocketconnection.inc.php";
require_once __DIR__ . "/incl/webui.inc.php";


//If invalid settings are set, load setup
if (checkExtensionsInstalled()["result"] == RESULT_REQ_MISSING || !isGrocyApiSet()) {
    header("Location: setup.php");
    die();
}

//If barcodes or parameters are passed through CLI or GET, process them and do not do anything else
if (isset($_GET["version"]) || (isset($argv[1]) && $argv[1]=="-v")) {
   die("BarcodeBuddy ".BB_VERSION);
}


//If arguments are passed with the CLI, parse them as barcode
if (isset($argv[1])) {
    processNewBarcode(sanitizeString($argv[1], true));
    die;
}

//If mode was set with GET parameter
if (isset($_GET["mode"])) {
    processModeChangeGetParameter($_GET["mode"]);
    hideGetPostParameters();
}

//Called if a new product was created through the idea
if (isset($_GET["refreshbarcode"])) {
    processRefreshedBarcode(sanitizeString($_GET["refreshbarcode"]));
    hideGetPostParameters();
}


//If barcode was submitted with GET parameter "add"
if (isset($_GET["add"])) {
    processNewBarcode(sanitizeString($_GET["add"], true));
    if (!isset($_GET["showui"])) {
        die("OK");
    }
    hideGetPostParameters();
}

//If barcode was submitted with GET parameter "text"
if (isset($_GET["text"])) {
    processNewBarcode(sanitizeString($_GET["text"], true));
    if (!isset($_GET["showui"])) {
        die("OK");
    }
    hideGetPostParameters();
}


// If a button was pressed, we are processing everything here.
// Only one row can be processed at a time
processButtons();

$barcodes = $db->getStoredBarcodes();
		if (sizeof($barcodes['known']) > 0 || sizeof($barcodes['unknown']) > 0) {
		    $productinfo = API::getProductInfo();
		}

//Only pass refreshed cards to AJAX
if (isset($_GET["ajaxrefresh"])) {
    $returnArray = array("f1" => getHtmlMainMenuTableKnown($barcodes),
                         "f2" => getHtmlMainMenuTableUnknown($barcodes),
                         "f3" => getHtmlLogTextArea());
    echo json_encode($returnArray, JSON_HEX_QUOT);
    die();
}


$webUi = new WebUiGenerator(MENU_MAIN);
$webUi->addHeader();
$webUi->addCard("New Barcodes",getHtmlMainMenuTableKnown($barcodes),"Delete all",'window.location.href=\''.$_SERVER['PHP_SELF'].'?delete=known\'');
$webUi->addCard("Unknown Barcodes",getHtmlMainMenuTableUnknown($barcodes),"Delete all",'window.location.href=\''.$_SERVER['PHP_SELF'].'?delete=unknown\'');
$webUi->addCard("Processed Barcodes",getHtmlLogTextArea(),"Clear log",'window.location.href=\''.$_SERVER['PHP_SELF'].'?delete=log\'');
$webUi->addFooter();
$webUi->printHtml();


//Check if a button on the web ui was pressed and process
function processButtons() {
    global $db;
    
    if (isset($_GET["delete"])) {
        $db->deleteAll($_GET["delete"]);
        //Hide get
        header("Location: " . $_SERVER["PHP_SELF"]);
        die();
    }
    
    if (isset($_POST["button_delete"])) {
        $id = $_POST["button_delete"];
        checkIfNumeric($id);
        $db->deleteBarcode($id);
        //Hide POST, so we can refresh
        header("Location: " . $_SERVER["PHP_SELF"]);
        die();
    }
    
    
    if (isset($_POST["button_delete"])) {
        $id = $_POST["button_delete"];
        checkIfNumeric($id);
        $db->deleteBarcode($id);
        //Hide POST, so we can refresh
        header("Location: " . $_SERVER["PHP_SELF"]);
        die();
    }
    
    if (isset($_POST["button_add_manual"])) {
        if (isset($_POST["newbarcodes"]) && strlen(trim($_POST["newbarcodes"])) > 0) {
            $barcodes = explode("\n", trim($_POST['newbarcodes']));
            foreach ($barcodes as $barcode) {
        $trimmedBarcode = trim(sanitizeString($barcode));
        if (strlen($trimmedBarcode)>0) {
                    processNewBarcode($trimmedBarcode, true);
        }
            }
        }
        
        //Hide POST, so we can refresh
        header("Location: " . $_SERVER["PHP_SELF"]);
        die();
    }
    
    if (isset($_POST["button_add"]) || isset($_POST["button_consume"])) {
        if (isset($_POST["button_consume"])) {
            $isConsume = true;
            $id        = $_POST["button_consume"];
        } else {
            $isConsume = false;
            $id        = $_POST["button_add"];
        }
        checkIfNumeric($id);
        $gidSelected = $_POST["select_" . $id];
        if ($gidSelected != 0) {
            $row = $db->getBarcodeById($id);
            if ($row !== false) {
                $barcode = sanitizeString($row["barcode"], true);
                $amount  = $row["amount"];
                checkIfNumeric($amount);
                if (isset($_POST["tags"])) {
                    foreach ($_POST["tags"][$id] as $tag) {
                    $db->addTag(sanitizeString($tag), $gidSelected);
                    }
                }
                $product = API::getProductInfo(sanitizeString($gidSelected));
                $previousBarcodes = $product["barcode"];
                if ($previousBarcodes == NULL) {
                    API::setBarcode($gidSelected, $barcode);
                } else {
                    API::setBarcode($gidSelected, $previousBarcodes . "," . $barcode);
                }
                $db->deleteBarcode($id);
                if ($isConsume) {
                    API::consumeProduct($gidSelected, $amount);
                } else {
                    API::purchaseProduct($gidSelected, $amount);
                }
                $db->refreshQuantityProductName($barcode, $product["name"]);
            }
       }
        //Hide POST, so we can refresh
        header("Location: " . $_SERVER["PHP_SELF"]);
        die();
    }
}

?>
