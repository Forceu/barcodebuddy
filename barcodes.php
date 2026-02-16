<?php

/**
 * Barcode Buddy for Grocy
 *
 * PHP version 8
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.8.1.9
 */

require_once __DIR__ . "/incl/configProcessing.inc.php";
require_once __DIR__ . "/incl/db.inc.php";
require_once __DIR__ . "/incl/webui.inc.php";
require_once __DIR__ . "/incl/api.inc.php";

const MODE_ACTION   = 'act';
const MODE_QUANTITY = 'qty';
const MODE_LOCATION = 'loc';

$CONFIG->checkIfAuthenticated(true, true);

// Get mode and validate it
$mode = MODE_ACTION;
if (isset($_GET)) {
    if (isset($_GET["mode"])) {
        $mode = $_GET["mode"];
    }
}
if (!in_array($mode, [MODE_ACTION ,MODE_QUANTITY, MODE_LOCATION])) {
    die("Invalid mode");
}

// Generate the page
$webUi = new WebUiGenerator(MENU_GENERIC);

$webUi->addBaseHeader(
    null,
    false,
    true,
    "<script src=\"/incl/js/JsBarcode.all.min.js\"></script>\n<script src=\"/incl/js/scripts_barcodes.js\"></script>");

switch ($mode) {
    case MODE_ACTION:
        getHtmlActionTable($webUi);
        break;
    case MODE_QUANTITY:
        getHtmlQuantityTable($webUi);
        break;
    case MODE_LOCATION:
        getHtmlLocationTable($webUi);
        break;
}

$webUi->printHtml();

function getHtmlActionTable(WebUiGenerator $webUi): void
{
    $config = BBConfig::getInstance();

    $actions = [
        1 => ['barcode' => $config['BARCODE_C'], 'name' => 'Consume'],
        2 => ['barcode' => $config['BARCODE_CS'], 'name' => 'Consume (spoiled)'],
        3 => ['barcode' => $config['BARCODE_CA'], 'name' => 'Consume All'],
        4 => ['barcode' => $config['BARCODE_P'], 'name' => 'Purchase'],
        5 => ['barcode' => $config['BARCODE_O'], 'name' => 'Open'],
        6 => ['barcode' => $config['BARCODE_GS'], 'name' => 'Inventory'],
        7 => ['barcode' => $config['BARCODE_AS'], 'name' => 'Add to Shopping List'],
    ];


    // Generate the HTML
    $html   = new UiEditor(true, null, "barcodes");
    $html->addHtml("<div id=\"action-barcodes\" class=\"flex-settings\" data-actions='" . json_encode($actions) . "'>");

    foreach ($actions as $key => $action) {
        $html->addDiv("<a href=\"#\" onclick=\"downloadBarcode('action-$key'); return false;\"><img id=\"action-$key\" alt=\"$key\" data-name=\"{$action['name']}\"/></a>", null, "flex-settings-child");

    }

    $html->addHtml('</div>');
    $webUi->addHtml($html->getHtml());

    // Generate the JS
    $webUi->addScript("generateActionBarcodes();");
}

function getHtmlLocationTable(WebUiGenerator $webUi): void
{
    $config = BBConfig::getInstance();
    $locations = API::getLocations();

    // Generate the HTML
    $html   = new UiEditor(true, null, "barcodes");
    $html->addHtml("<div id=\"location-barcodes\" class=\"flex-settings\" data-locations='" . json_encode($locations) . "' data-barcode='" . $config['BARCODE_TXFR'] . "'>");

    foreach ($locations as $location) {
        $html->addDiv("<a href=\"#\" onclick=\"downloadBarcode('location-$location->id'); return false;\"><img id=\"location-$location->id\" alt=\"$location->id\" data-name=\"{$location->name}\"/></a>", null, "flex-settings-child");

    }

    $html->addHtml('</div>');
    $webUi->addHtml($html->getHtml());

    // Generate the JS
    $webUi->addScript("generateLocationBarcodes();");
}


function getHtmlQuantityTable(WebUiGenerator $webUi): void
{
    $config = BBConfig::getInstance();

    // Get quantity start and end
    $startQty = isset($_GET['startQty']) ? intval($_GET['startQty']) : 1;
    $endQty = isset($_GET['endQty']) ? intval($_GET['endQty']) : 10;

    // Generate the HTML
    $html   = new UiEditor(true, null, "barcodes");
    $html->addHtml("<div id=\"quantity-barcodes\" class=\"flex-settings\" data-start-qty='$startQty' data-end-qty='$endQty' data-barcode='" . $config['BARCODE_Q'] . "'>");

    for ($i = $startQty; $i <= $endQty; $i++) {
        $html->addDiv("<a href=\"#\" onclick=\"downloadBarcode('quantity-$i'); return false;\"><img id=\"quantity-$i\" alt=\"$i\" data-name=\"Quantity $i\"/></a>", null, "flex-settings-child");
    }

    $html->addHtml('</div>');
    $webUi->addHtml($html->getHtml());

    // Generate the JS
    $webUi->addScript("generateQuantityBarcodes();");
}