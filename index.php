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
 * Index file that receives barcodes and displays web UI
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */

require_once __DIR__ . "/incl/configProcessing.inc.php";
require_once __DIR__ . "/incl/api.inc.php";
require_once __DIR__ . "/incl/db.inc.php";
require_once __DIR__ . "/incl/internalChecking.inc.php";
require_once __DIR__ . "/incl/processing.inc.php";
require_once __DIR__ . "/incl/websocketconnection.inc.php";
require_once __DIR__ . "/incl/webui.inc.php";

//If barcodes or parameters are passed through CLI or GET, process them and do not do anything else
if (isset($_GET["version"]) || (isset($argv[1]) && $argv[1]=="-v")) {
   die("BarcodeBuddy ".BB_VERSION);
}


//If arguments are passed with the CLI, parse them as barcode
if (isset($argv[1])) {
    processNewBarcode(sanitizeString($argv[1], true));
    die;
}



$CONFIG->checkIfAuthenticated(true);

//If invalid settings are set, load setup
if (checkExtensionsInstalled()["result"] == RESULT_REQ_MISSING || !isGrocyApiSet()) {
    header("Location: setup.php");
    die();
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
$webUi->addHeader('<link rel="stylesheet" href="./incl/css/styleMain.css">');

$link = (new MenuItemLink())
                ->setText("Delete all")
                ->setLink('window.location.href=\''.$_SERVER['PHP_SELF'].'?delete=known\'');
$webUi->addCard("New Barcodes",getHtmlMainMenuTableKnown($barcodes), $link);

$link = (new MenuItemLink())
                ->setText("Delete all")
                ->setLink('window.location.href=\''.$_SERVER['PHP_SELF'].'?delete=unknown\'');
$webUi->addCard("Unknown Barcodes",getHtmlMainMenuTableUnknown($barcodes), $link);

$link = (new MenuItemLink())
                ->setText("Clear log")
                ->setLink('window.location.href=\''.$_SERVER['PHP_SELF'].'?delete=log\'');
$webUi->addCard("Processed Barcodes",getHtmlLogTextArea(), $link);
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
                if (strlen($trimmedBarcode) > 0) {
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
                $product          = API::getProductInfo(sanitizeString($gidSelected));
                $previousBarcodes = $product["barcode"];
                if ($previousBarcodes == NULL) {
                    API::setBarcode($gidSelected, $barcode);
                } else {
                    API::setBarcode($gidSelected, $previousBarcodes . "," . $barcode);
                }
                outputLog("Associated barcode $barcode with " . $product["name"], EVENT_TYPE_ASSOCIATE_PRODUCT, true, false);
                $db->deleteBarcode($id);
                if ($isConsume) {
                    if ($product["stockAmount"] < $amount) 
                            $amount = $product["stockAmount"];
                        API::consumeProduct($gidSelected, $amount);
                        outputLog("Consuming $amount " . $product["unit"] . " of " . $product["name"], EVENT_TYPE_ADD_KNOWN_BARCODE, false, false);
                } else {
                    API::purchaseProduct($gidSelected, $amount);
                    outputLog("Adding $amount " . $product["unit"] . " of " . $product["name"], EVENT_TYPE_ADD_KNOWN_BARCODE, false, false);
                }
                $db->refreshQuantityProductName($barcode, $product["name"]);
            }
        }
        //Hide POST, so we can refresh
        header("Location: " . $_SERVER["PHP_SELF"]);
        die();
    }
}



//Generate the table with barcodes
function getHtmlMainMenuTableKnown($barcodes) {
    global $productinfo;
    global $BBCONFIG;
    $html = new UiEditor(true, null, "f1");
    if (sizeof($barcodes['known']) == 0) {
        $html->addHtml("No known barcodes yet.");
        return $html->getHtml();
    } else {
        $table        = new TableGenerator(array(
            "Name",
            "Barcode",
            "Quantity",
            "Product",
            "Action",
            "Tags",
            "Create",
            "Remove"
        ));
        foreach ($barcodes['known'] as $item) {
            $isDisabled = "disabled";
            if ($item['match'] != 0) {
                $isDisabled = "";
            }
            $itemId = $item['id'];
            $table->startRow();
            $table->addCell($item['name']);
            $table->addCell($item['barcode']);
            $table->addCell($item['amount']);
            $table->addCell('<select  onchange=\'enableButton("select_' . $itemId . '", "button_add_' . $item['id'] . '", "button_consume_' . $item['id'] . '")\' id="select_' . $itemId . '" name="select_' . $itemId . '">' . printSelections($item['match'], $productinfo) . '</select>');
            $table->addCell($html->buildButton("button_add", "Add")
                                ->setDisabled($isDisabled)
                                ->setSubmit()
                                ->setRaised()
                                ->setIsAccent()
                                ->setValue($item['id'])
                                ->setId('button_add_' . $item['id'])
                                ->generate(true). ' ' . 
                            $html->buildButton("button_consume", "Consume")
                                ->setDisabled($isDisabled)
                                ->setSubmit()
                                ->setRaised()
                                ->setIsAccent()
                                ->setValue($item['id'])
                                ->setId('button_consume_' . $item['id'])
                                ->generate(true));
            $table->addCell(explodeWordsAndMakeCheckboxes($item['name'], $itemId));
            $table->addCell($html->buildButton("button_createproduct", "Create Product")
                                                ->setOnClick('openNewTab(\''.$BBCONFIG["GROCY_BASE_URL"].'product/new?closeAfterCreation&prefillname='.rawurlencode(htmlspecialchars_decode($item['name'],ENT_QUOTES)).'&prefillbarcode='.$item['barcode'].'\', \''.$item['barcode'].'\')')
                                                ->generate(true));
            $table->addCell($html->buildButton("button_delete", "Remove")->setSubmit()->setValue($item['id'])->generate(true));
            $table->endRow();
        }
        $html->addTableClass($table);
        return $html->getHtml();
    }
}



//Generate the table with barcodes
function getHtmlMainMenuTableUnknown($barcodes) {
    global $BBCONFIG;
    global $productinfo;
    $html = new UiEditor(true, null, "f2");
    if (sizeof($barcodes['unknown']) == 0) {
        $html->addHtml("No known barcodes yet.");
        return $html->getHtml();
    } else {
        $table        = new TableGenerator(array(
            "Barcode",
            "Look up",
            "Quantity",
            "Product",
            "Action",
            "Create",
            "Remove"
        ));
        foreach ($barcodes['unknown'] as $item) {
            $isDisabled = "disabled";
            if ($item['match'] != 0) {
                $isDisabled = "";
            }
            $itemId = $item['id'];
            $table->startRow();
            $table->addCell($item['barcode']);
            $table->addCell('<a href="http://google.com/search?q=' . $item['barcode'] . '" target="_blank">Search for barcode</a>');
            $table->addCell($item['amount']);
            $table->addCell('<select onchange=\'enableButton("select_' . $itemId . '", "button_add_' . $item['id'] . '", "button_consume_' . $item['id'] . '")\' id="select_' . $itemId . '" name="select_' . $itemId . '">' . printSelections($item['match'], $productinfo) . '</select>');
            $table->addCell($html->buildButton("button_add", "Add")
                                ->setDisabled($isDisabled)
                                ->setSubmit()
                                ->setRaised()
                                ->setIsAccent()
                                ->setValue($item['id'])
                                ->setId('button_add_' . $item['id'])
                                ->generate(true). ' ' . 
                            $html->buildButton("button_consume", "Consume")
                                ->setDisabled($isDisabled)
                                ->setSubmit()
                                ->setRaised()
                                ->setIsAccent()
                                ->setValue($item['id'])
                                ->setId('button_consume_' . $item['id'])
                                ->generate(true));
            $table->addCell($html->buildButton("button_createproduct", "Create Product")
                                                ->setOnClick('openNewTab(\''.$BBCONFIG["GROCY_BASE_URL"].'product/new?closeAfterCreation&prefillbarcode='.$item['barcode'].'\', \''.$item['barcode'].'\')')
                                                ->generate(true));
            $table->addCell($html->buildButton("button_delete", "Remove")->setSubmit()->setValue($item['id'])->generate(true));
            $table->endRow();
        }
        $html->addTableClass($table);
        return $html->getHtml();
    }
}



//outputs stored logs to the textarea
function getHtmlLogTextArea() {
    global $db;
    $logs = $db->getLogs();
    $html = new UiEditor(true, null, "f3");
    if (sizeof($logs) == 0) {
        $html->addHtml("No processed items yet.");
        return $html->getHtml();
    } else {
        $html->addHtml('<div style="
            -moz-appearance: textfield-multiline;
            -webkit-appearance: textarea;
                    max-height: 18em;
                    overflow:auto;"
            contenteditable="true"
            ondrop="return false"
            oncut="return false"
            onpaste="return false"
            onkeydown="if(event.metaKey) return true; return false;">');
        foreach ($logs as $log) {
            $html->addHtml($log . "<br>");
        }
        $html->addHtml('</div>');
        return $html->getHtml();
    }
}


?>
