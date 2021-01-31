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
require_once __DIR__ . "/incl/config.inc.php";
require_once __DIR__ . "/incl/internalChecking.inc.php";
require_once __DIR__ . "/incl/processing.inc.php";
require_once __DIR__ . "/incl/websocketconnection.inc.php";
require_once __DIR__ . "/incl/webui.inc.php";

//If barcodes or parameters are passed through CLI or GET, process them and do not do anything else
if (isset($_GET["version"]) || (isset($argv[1]) && $argv[1] == "-v")) {
    die("BarcodeBuddy " . BB_VERSION);
}

//Run API benchmark when the parameter --benchmark is supplied
if (isset($argv[1]) && $argv[1] == "--benchmark") {
    if (isset($argv[2]) && is_numeric($argv[2]))
        API::runBenchmark($argv[2]);
    else
        die("Benchmark requires a numeric parameter with the product ID to test, eg 'php index.php --benchmark 1'");
}

//If arguments are passed with the CLI, parse them as barcode
if (isset($argv[1])) {
    processNewBarcode(sanitizeString($argv[1], true));
    die;
}

$CONFIG->checkIfAuthenticated(true);

//If invalid settings are set, load setup
if (checkExtensionsInstalled()["result"] == RESULT_REQ_MISSING || !isGrocyApiSet()) {
    header("Location: ./setup.php");
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

//After clicking on buttons on Federation popup
if (isset($_GET["fed"])) {
    if ($_GET["fed"] == 1) {
        BarcodeFederation::enableFederation();
    } else {
        BarcodeFederation::disableFederation();
    }
    hideGetPostParameters();
}

// If a button was pressed, we are processing everything here.
// Only one row can be processed at a time
processButtons();

$barcodes = DatabaseConnection::getInstance()->getStoredBarcodes();
if (sizeof($barcodes['known']) > 0 || sizeof($barcodes['unknown']) > 0 || sizeof($barcodes['tare']) > 0) {
    $productinfo = API::getAllProductsInfo();
}

//Only pass refreshed cards to AJAX
if (isset($_GET["ajaxrefresh"])) {
    $returnArray = array("f1" => getHtmlMainMenuTableKnown($barcodes),
                         "f2" => getHtmlMainMenuTableUnknown($barcodes),
                         "f3" => getHtmlLogTextArea(),
                         "f4" => getHtmlMainMenuReqActions($barcodes));
    echo json_encode($returnArray, JSON_HEX_QUOT);
    die();
}

$webUi = new WebUiGenerator(MENU_MAIN);
$webUi->addHeader('<link rel="stylesheet" href="./incl/css/styleMain.css?v=' . BB_VERSION . '">', true);


$link = (new MenuItemLink())
    ->setText("Delete all")
    ->setLink('window.location.href=\'' . $CONFIG->getPhpSelfWithBaseUrl() . '?delete=req_actions\'');
if (sizeof($barcodes['tare']) > 0) {
    $webUi->addCard("Action required", getHtmlMainMenuReqActions($barcodes), $link);
}

$link = (new MenuItemLink())
    ->setText("Delete all")
    ->setLink('window.location.href=\'' . $CONFIG->getPhpSelfWithBaseUrl() . '?delete=known\'');
$webUi->addCard("New Barcodes", getHtmlMainMenuTableKnown($barcodes), $link);

$link = (new MenuItemLink())
    ->setText("Delete all")
    ->setLink('window.location.href=\'' . $CONFIG->getPhpSelfWithBaseUrl() . '?delete=unknown\'');
$webUi->addCard("Unknown Barcodes", getHtmlMainMenuTableUnknown($barcodes), $link);

$link = (new MenuItemLink())
    ->setText("Clear log")
    ->setLink('window.location.href=\'' . $CONFIG->getPhpSelfWithBaseUrl() . '?delete=log\'');
$webUi->addCard("Processed Barcodes", getHtmlLogTextArea(), $link);
$webUi->addScript("updateRedisCacheAndFederation(false)");
$webUi->addFooter();
displayFederationPopupHtml($webUi);
$webUi->printHtml();

function displayFederationPopupHtml(WebUiGenerator &$webUi) {
    $config = BBConfig::getInstance();
    if (!$config["BBUDDY_SERVER_ENABLED"] && !$config["BBUDDY_SERVER_POPUPSHOWN"]) {
        $webUi->addConfirmDialog("We are proud to introduce Barcode Buddy Federation, which enables you to lookup " .
            "barcodes that other users have already associated with a Grocy product before. The lookup is very fast " .
            "(around 100ms, depending on your location) and should be quite accurate.<br>It requires no signup, but " .
            "is limited to 200 requests per day.<br><br><span style='font-size: smaller'><i>By using this method, you agree that all your Grocy product " .
            "names that are associated with barcodes, the barcodes itself, your IP address and an unique ID will be " .
            "sent and stored at a remote Barcode Buddy server located in Germany. No other data is sent or stored and " .
            "no data will be used for commercial purposes. The sourcecode for the server application is available " .
            "<a href='https://github.com/Forceu/barcodebuddy-federation/'>on Github</a> and can be selfhosted as well.</i>" .
            "</span><br><br><b>Would you like to enable Barcode Buddy Federation?</b>",
            "if (result) { location.href='./index.php?fed=1'; } else { location.href='./index.php?fed=0';}",
            "Introducing Barcode Buddy Federation", "Use Federation", "Cancel", "large");
    }
}

//Check if a button on the web ui was pressed and process
function processButtons() {
    global $CONFIG;
    $db = DatabaseConnection::getInstance();

    if (isset($_GET["delete"])) {
        $db->deleteAll($_GET["delete"]);
        //Hide get
        header("Location: " . $CONFIG->getPhpSelfWithBaseUrl());
        die();
    }

    if (isset($_POST["button_delete"])) {
        $id = $_POST["button_delete"];
        checkIfNumeric($id);
        $db->deleteBarcode($id);
        //Hide POST, so we can refresh
        header("Location: " . $CONFIG->getPhpSelfWithBaseUrl());
        die();
    }

    if (isset($_POST["button_submit"])) {
        $id = $_POST["button_submit"];
        checkIfNumeric($id);
        if (isset($_POST["quantity_" . $id])) {
            checkIfNumeric($_POST["quantity_" . $id]);
            $barcode = $db->getBarcodeById($id);
            if (changeWeightTareItem($barcode["barcode"], $_POST["quantity_" . $id]) == true) {
                $db->deleteBarcode($id);
            }
        }
        header("Location: " . $CONFIG->getPhpSelfWithBaseUrl());
    }

    if (isset($_POST["button_add_manual"])) {
        if (isset($_POST["newbarcodes"]) && strlen(trim($_POST["newbarcodes"])) > 0) {
            $barcodes = explode("\n", trim($_POST['newbarcodes']));
            foreach ($barcodes as $barcode) {
                $trimmedBarcode = trim(sanitizeString($barcode));
                if (strlen($trimmedBarcode) > 0) {
                    processNewBarcode($trimmedBarcode);
                }
            }
        }

        //Hide POST, so we can refresh
        header("Location: " . $CONFIG->getPhpSelfWithBaseUrl());
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
                        TagManager::add(sanitizeString($tag), $gidSelected);
                    }
                }
                $product = API::getProductInfo(sanitizeString($gidSelected));
                API::addBarcode($gidSelected, $barcode);
                $log = new LogOutput("Associated barcode $barcode with " . $product->name, EVENT_TYPE_ASSOCIATE_PRODUCT);
                $log->setVerbose()->dontSendWebsocket()->createLog();
                $db->deleteBarcode($id);
                QuantityManager::syncBarcodeToGrocy($barcode);
                if ($product->isTare) {
                    if (!$db->isUnknownBarcodeAlreadyStored($barcode))
                        $db->insertActionRequiredBarcode($barcode, $row["bestBeforeInDays"], $row["price"]);
                    $log = new LogOutput("Action required: Enter weight for " . $product->name, EVENT_TYPE_ACTION_REQUIRED, $barcode);
                    $log->setVerbose()->dontSendWebsocket()->createLog();
                } else {
                    if ($isConsume) {
                        if ($product->stockAmount < $amount)
                            $amount = $product->stockAmount;
                        if ($amount > 0) {
                            API::consumeProduct($gidSelected, $amount);
                            $log = new LogOutput("Consuming $amount " . $product->unit . " of " . $product->name, EVENT_TYPE_ADD_KNOWN_BARCODE);
                        } else {
                            $log = new LogOutput("None in stock, not consuming: " . $product->name, EVENT_TYPE_ADD_KNOWN_BARCODE);
                        }
                        $log->setVerbose()->dontSendWebsocket()->createLog();
                    } else {
                        $additionalLog = "";
                        if (!API::purchaseProduct($gidSelected, $amount, $row["bestBeforeInDays"], $row["price"])) {
                            $additionalLog = " [WARNING]: No default best before date set!";
                        }
                        $log = new LogOutput("Adding $amount " . $product->unit . " of " . $product->name . $additionalLog, EVENT_TYPE_ADD_KNOWN_BARCODE);
                        $log->setVerbose()->dontSendWebsocket()->createLog();
                    }
                }
            }
        }
        //Hide POST, so we can refresh
        header("Location: " . $CONFIG->getPhpSelfWithBaseUrl());
        die();
    }
}


/**
 * Generate the table with barcodes that require actions
 * @param array $barcodes
 * @return string
 */
function getHtmlMainMenuReqActions(array $barcodes): string {
    $html = new UiEditor(true, null, "f4");
    if (sizeof($barcodes['tare']) == 0) {
        return "null";
    } else {
        $table = new TableGenerator(array(
            "Name",
            "Current Weight",
            "Input",
            "Action",
            "Remove"
        ));
        foreach ($barcodes['tare'] as $item) {
            $product     = API::getProductByBarcode($item['barcode']);
            $totalWeight = $product->stockAmount + $product->tareWeight;
            $table->startRow();
            $table->addCell($product->name);
            $table->addCell($totalWeight);
            $table->addCell($html->buildEditField("quantity_" . $item['id'], "New weight (tare: " . intval($product->tareWeight) . ")", $totalWeight)
                ->type("number")
                ->setWidth('8em')
                ->minmax(array($product->tareWeight, null))
                ->generate(true));
            $table->addCell($html->buildButton("button_submit", "Submit")
                ->setSubmit()
                ->setRaised()
                ->setIsAccent()
                ->setValue($item['id'])
                ->setId('button_submit_' . $item['id'])
                ->generate(true));
            $table->addCell($html->buildButton("button_delete", "Remove")->setSubmit()->setValue($item['id'])->generate(true));
            $table->endRow();
        }
        $html->addTableClass($table);
        return $html->getHtml();
    }
}


/**
 * Generate the table with barcodes
 * @param array $barcodes
 * @return string
 */
function getHtmlMainMenuTableKnown(array $barcodes): string {
    global $productinfo;

    $html = new UiEditor(true, null, "f1");
    if (sizeof($barcodes['known']) == 0) {
        $html->addHtml("No known barcodes yet.");
        return $html->getHtml();
    } else {

        $containsFederationName = false;
        foreach ($barcodes['known'] as $item) {
            if ($item['bbServerAltNames'] != null)
                $containsFederationName = true;
        }
        $arrayTableEntries = array(
            "Name",
            "Barcode",
            "Quantity",
            "Product",
            "Action",
            "Tags",
            "Create",
            "Remove");
        if ($containsFederationName)
            array_splice($arrayTableEntries, 1, 0, array("Federation"));

        $table = new TableGenerator($arrayTableEntries);
        foreach ($barcodes['known'] as $item) {
            $isDisabled = "disabled";
            if ($item['match'] != 0) {
                $isDisabled = "";
            }
            $itemId = $item['id'];
            $table->startRow();
            $bbServerButton = "";
            if ($item['bbServerAltNames'] != null) {
                if (sizeof($item['bbServerAltNames']) > 1)
                    $bbServerButton = getVoteButton($item) . getReportButton($item);
                else
                    $bbServerButton = getReportButton($item);
            }
            $table->addCell(UiEditor::addTextWrap($item['name'], 15));
            if ($containsFederationName)
                $table->addCell($bbServerButton);
            $table->addCell($item['barcode']);
            $table->addCell($item['amount']);
            $table->addCell('<select style="max-width: 20em;" onchange=\'enableButton("select_' . $itemId . '", "button_add_' . $item['id'] . '", "button_consume_' . $item['id'] . '")\' id="select_' . $itemId . '" name="select_' . $itemId . '">' . printSelections($item['match'], $productinfo) . '</select>');
            $table->addCell($html->buildButton("button_add", "Add")
                    ->setDisabled($isDisabled)
                    ->setSubmit()
                    ->setRaised()
                    ->setIsAccent()
                    ->setValue($item['id'])
                    ->setId('button_add_' . $item['id'])
                    ->generate(true) . ' ' .
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
                ->setOnClick('openNewTab(\'' . BBConfig::getInstance()["GROCY_BASE_URL"] . 'product/new?closeAfterCreation&flow=InplaceNewProductWithName&name=' . rawurlencode(htmlspecialchars_decode($item['name'], ENT_QUOTES)) . '\', \'' . $item['barcode'] . '\')')
                ->generate(true));
            $table->addCell($html->buildButton("button_delete", "Remove")->setSubmit()->setValue($item['id'])->generate(true));
            $table->endRow();
        }
        $html->addTableClass($table);
        return $html->getHtml();
    }
}

function getReportButton(array $item): string {
    return '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="showReportFederationName(\'' . $item['barcode'] . '\',\'' . $item['name'] . '\')"><span class="icon-flag"></span></button>';
}


function getVoteButton(array $item): string {
    return '<button type="button" class="btn btn-primary  btn-sm" onclick="showMultipleFederationNames(\'' . $item['barcode'] . '\',\'' . base64_encode(json_encode($item['bbServerAltNames'])) . '\')"><span class="icon-navigation-more"></span></button> ';
}


/**
 * Generate the table with barcodes
 * @param array $barcodes
 * @return string
 */
function getHtmlMainMenuTableUnknown(array $barcodes): string {
    global $productinfo;
    global $CONFIG;
    $html = new UiEditor(true, null, "f2");
    if (sizeof($barcodes['unknown']) == 0) {
        $html->addHtml("No unknown barcodes yet.");
        return $html->getHtml();
    } else {
        $table = new TableGenerator(array(
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
            $table->addCell('<a href="' . $CONFIG->SEARCH_ENGINE . $item['barcode'] . '" target="_blank">Search for barcode</a>');
            $table->addCell($item['amount']);
            $table->addCell('<select style="max-width: 20em;" onchange=\'enableButton("select_' . $itemId . '", "button_add_' . $item['id'] . '", "button_consume_' . $item['id'] . '")\' id="select_' . $itemId . '" name="select_' . $itemId . '">' . printSelections($item['match'], $productinfo) . '</select>');
            $table->addCell($html->buildButton("button_add", "Add")
                    ->setDisabled($isDisabled)
                    ->setSubmit()
                    ->setRaised()
                    ->setIsAccent()
                    ->setValue($item['id'])
                    ->setId('button_add_' . $item['id'])
                    ->generate(true) . ' ' .
                $html->buildButton("button_consume", "Consume")
                    ->setDisabled($isDisabled)
                    ->setSubmit()
                    ->setRaised()
                    ->setIsAccent()
                    ->setValue($item['id'])
                    ->setId('button_consume_' . $item['id'])
                    ->generate(true));
            $table->addCell($html->buildButton("button_createproduct", "Create Product")
                ->setOnClick('openNewTab(\'' . BBConfig::getInstance()["GROCY_BASE_URL"] . 'product/new?closeAfterCreation&prefillbarcode=' . $item['barcode'] . '\', \'' . $item['barcode'] . '\')')
                ->generate(true));
            $table->addCell($html->buildButton("button_delete", "Remove")->setSubmit()->setValue($item['id'])->generate(true));
            $table->endRow();
        }
        $html->addTableClass($table);
        return $html->getHtml();
    }
}


/**
 * Outputs stored logs to the textarea
 * @return string
 * @throws DbConnectionDuringEstablishException
 */
function getHtmlLogTextArea(): string {
    $db   = DatabaseConnection::getInstance();
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
            contenteditable="false">');
        foreach ($logs as $log) {
            $html->addHtml($log . "<br>");
        }
        $html->addHtml('</div>');
        return $html->getHtml();
    }
}
