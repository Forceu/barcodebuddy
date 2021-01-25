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
 * @since      File available since Release 1.8
 */

$isVote   = isset($_GET["voteFederation"]);
$isReport = isset($_GET["reportFederation"]);

if ($isVote || $isReport) {
    require_once __DIR__ . "/modules/barcodeServer.php";
    if (!isset($_POST["barcode"]) || !isset($_POST["name"]))
        die("Illegal request");

    $barcode = $_POST["barcode"];
    $name    = $_POST["name"];
    if ($isVote)
        echo BarcodeServer::voteName($barcode, $name);
    else
        echo BarcodeServer::reportName($barcode, $name);
}