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
 * @since      File available since Release 1.2
 */


/**
 * Setup file
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.2
 *
 */

require_once __DIR__ . "/incl/config.php";
require_once __DIR__ . "/incl/db.inc.php";
require_once __DIR__ . "/incl/webui.inc.php";
require_once __DIR__ . "/incl/api.inc.php";
require_once __DIR__ . "/incl/processing.inc.php";
require_once __DIR__ . "/incl/internalChecking.inc.php";

$result = true;

if (isset($_POST["GROCY_API_URL"])) {
    $apiWithTrailingSlash = rtrim($_POST["GROCY_API_URL"], '/') . '/';
    $result = checkApiConnection($apiWithTrailingSlash, $_POST["GROCY_API_KEY"]);
    if ($result === true) {
        $db->updateConfig("GROCY_API_URL", sanitizeString($apiWithTrailingSlash));
        $db->updateConfig("GROCY_API_KEY", sanitizeString($_POST["GROCY_API_KEY"]));
        header("Location: index.php");
    } 
    
}



$webUi = new WebUiGenerator(MENU_SETUP);
$webUi->addHeader();
if (checkExtensionsInstalled()["result"] == RESULT_REQ_MISSING) {
    $webUi->addCard("Setup", getHtmlSetupExtMissing());
} else {
    $webUi->addCard("Setup", getHtmlSetupTable($result));
}
$webUi->addFooter();
$webUi->printHtml();

?>
