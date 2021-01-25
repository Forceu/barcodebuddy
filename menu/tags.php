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
 * List all tags
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

//Delete tag
if (isset($_POST["button_delete"])) {
    $id = $_POST["button_delete"];
    checkIfNumeric($id);
    TagManager::delete($id);
    //Hide POST, so we can refresh
    header("Location: " . $CONFIG->getPhpSelfWithBaseUrl());
    die();
}


$webUi = new WebUiGenerator(MENU_GENERIC);
$webUi->addHeader();
$webUi->addCard("Active Tags", getHtmlTagTable(true));
$webUi->addCard("Inactive Tags", getHtmlTagTable(false));
$webUi->addFooter();
$webUi->printHtml();


function getHtmlTagTable($isActive) {
    $allTags = getAllTags();

    if ($isActive) {
        $tags = $allTags["active"];
        $rows = array("Tag", "Product", "Action");
    } else {
        $tags = $allTags["inactive"];
        $rows = array("Tag", "Action");
    }
    $html = new UiEditor();
    if (sizeof($tags) == 0) {
        $html->addHtml("No tags stored.");
        return $html->getHtml();
    } else {
        $table = new TableGenerator($rows);

        foreach ($tags as $tag) {
            $table->startRow();
            $table->addCell($tag->name);
            if ($isActive)
                $table->addCell($tag->item);
            $table->addCell($html->buildButton("button_delete", "Delete")
                ->setSubmit()
                ->setValue($tag->id)
                ->generate(true));
            $table->endRow();
        }
        $html->addTableClass($table);
        return $html->getHtml();
    }
}

