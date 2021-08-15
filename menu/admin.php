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
 * @since      File available since Release 1.5
 */


require_once __DIR__ . "/../incl/configProcessing.inc.php";
require_once __DIR__ . "/../incl/processing.inc.php";
require_once __DIR__ . "/../incl/webui.inc.php";


$CONFIG->checkIfAuthenticated(true, true);


if (isset($_GET["download"])) {

    $dbfile = $CONFIG->DATABASE_PATH;
    $date   = date('Y-m-d_h-i-s');
    header('Content-Type: application/octet-stream');
    header("Content-Length: " . filesize($dbfile));
    header('Content-Disposition: attachment; filename="barcodebuddy-backup_' . $date . '.db"');
    readfile($dbfile);
    die();
}

$webUi = new WebUiGenerator(MENU_GENERIC);
$webUi->addHeader();
$webUi->addCard("Admin", getHtmlAdminMenu());
$webUi->addFooter();
$webUi->printHtml();


function getHtmlAdminMenu():string {
    global $CONFIG;
    $html = new UiEditor();
    $html->buildButton("button_backup", "Logout")
        ->setIsAccent()
        ->setOnClick("location.href='../logout.php'")
        ->setRaised()
        ->generate();
    $pathUsers = realpath($CONFIG->AUTHDB_PATH);
    $html->buildButton("button_backup", "Download Database")
        ->setOnClick("location.href='./admin.php?download'")
        ->setRaised()
        ->generate();
    $html->buildButton("button_backup", "Change Password")
        ->setOnClick("alert('If you would like to change your password, please delete the file $pathUsers')")
        ->setRaised()
        ->generate();
    return $html->getHtml();
}