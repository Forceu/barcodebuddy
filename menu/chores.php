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
 * List all chores
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
require_once __DIR__ . "/../incl/webui.inc.php";

$CONFIG->checkIfAuthenticated(true, true);


//Save tag if edit button was pressed
if (isset($_POST["button_edit"])) {
    $id = $_POST["button_edit"];
    checkIfNumeric($id);
    $barcode = sanitizeString($_POST["barcode_" . $id]);
    if ($barcode == "") {
        ChoreManager::deleteBarcode($id);
    } else {
        ChoreManager::updateBarcode($id, $barcode);
    }
    //Hide POST, so we can refresh
    header("Location: " . $CONFIG->getPhpSelfWithBaseUrl());
    die();
}


$webUi = new WebUiGenerator(MENU_GENERIC);
$webUi->addHeader();
$webUi->addCard("Chores", getHtmlChoreTable());
$webUi->addFooter();
$webUi->printHtml();


function getHtmlChoreTable() {
    $chores = getAllChores();
    $html   = new UiEditor();
    if (sizeof($chores) == 0) {
        $html->addHtml("No chores yet.");
        return $html->getHtml();
    } else {
        $table = new TableGenerator(array(
            "Chore",
            "Barcode",
            "Action"
        ));

        foreach ($chores as $chore) {
            $editText   = "Enter new barcode";
            $editValue  = "";
            $buttonText = "Add";
            $labelId    = "barcode_" . $chore['id'];
            $buttonId   = "button_" . $chore['id'];
            if ($chore['barcode'] != null) {
                $editText   = "Barcode will be deleted";
                $buttonText = "Edit";
                $editValue  = $chore['barcode'];
            }

            $table->startRow();
            $table->addCell($chore['name']);
            $table->addCell($html->buildEditField($labelId, $editText, $editValue)
                ->onKeyUp('enableButtonGen(\'' . $buttonId . '\', \'' . $labelId . '\', \'' . $editValue . '\')')
                ->required(false)
                ->setFloatingLabel(false)
                ->generate(true));
            $table->addCell($html->buildButton("button_edit", $buttonText)
                ->setDisabled()
                ->setSubmit()
                ->setId($buttonId)
                ->setRaised()
                ->setIsAccent()
                ->setValue($chore['id'])
                ->generate(true));
            $table->endRow();
        }
        $html->addTableClass($table);
        return $html->getHtml();
    }
}

