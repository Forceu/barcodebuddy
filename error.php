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
 * @since      File available since Release 1.3
 */


/**
 * Display errors
 *
 * 
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.3
 *
 */


/** This file should only be loaded if an error has occured
 *  The global variable $ERROR_MESSAGE needs to be set
 *  with the error message.
 */ 
if (!isset($ERROR_MESSAGE)) {
    header("Location: ./index.php");
}

require_once __DIR__ . "/incl/webui.inc.php";

$webUi = new WebUiGenerator(MENU_ERROR);
$webUi->addHeader();
$webUi->addCard("<b>Error</b>", $ERROR_MESSAGE);
$webUi->addFooter();
$webUi->printHtml();


