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
 *
 * Change quantities
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.2
 */

require_once __DIR__ . "/../incl/configProcessing.inc.php";
require_once __DIR__ . "/../incl/api.inc.php";
require_once __DIR__ . "/../incl/db.inc.php";
require_once __DIR__ . "/../incl/processing.inc.php";
require_once __DIR__ . "/../incl/websocketconnection.inc.php";
require_once __DIR__ . "/../incl/webui.inc.php";

$CONFIG->checkIfAuthenticated(true, true);

//Delete Quantity
if (isset($_POST["button_delete"])) {
    $id = $_POST["button_delete"];
    checkIfNumeric($id);
    API::deleteBarcodeQuantity($id);
    //Hide POST, so we can refresh
    header("Location: " . $CONFIG->getPhpSelfWithBaseUrl());
    die();
}


$webUi = new WebUiGenerator(MENU_GENERIC);
$webUi->addHeader();
$webUi->addCard("Saved Quantities", printSettingsQuantityTable());
$webUi->addFooter();
$webUi->printHtml();


function printSettingsQuantityTable(): string {
    global $CONFIG;
    $quantities = QuantityManager::getQuantities();
    sortQuantityArray($quantities);
    $html = new UiEditor();
    if (sizeof($quantities) == 0) {
        $html->addHtml("No saved quantities yet.");
        return $html->getHtml();
    } else {
        $returnString = '<form name="form" method="post" action="' . $CONFIG->getPhpSelfWithBaseUrl() . '" >';
        $table        = new TableGenerator(array(
            "Product",
            "Barcode",
            "Quantity",
            "Action"
        ));
        foreach ($quantities as $quantity) {
            $table->startRow();
            $table->addCell($quantity->product);
            $table->addCell($quantity->barcode);
            $table->addCell($quantity->quantity);
            $table->addCell($html->buildButton("button_delete", "Delete")
                ->setSubmit()
                ->setValue($quantity->id)
                ->generate(true));
            $table->endRow();
        }
        $html->addTableClass($table);
        return $html->getHtml();
    }

}

function sortQuantityArray(&$arr) {
    usort($arr, function (ApiQuantity $a, ApiQuantity $b) {
        return strtolower($a->product) <=> strtolower($b->product);
    });
}
