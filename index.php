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
 * @since      File available since Release 1.0
 */


/**
 * Index file that receives barcodes and displays web UI
 *
 * Make sure to modify API details. This script requires php-sqlite3 and php-curl
 * 
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 *
 * TODO:  - Disable button on web ui if "none" is selected
 *        - Refactor SQL code in this file to db.inc.php
 */





require_once "./config.php";
require_once "./api.inc.php";
require_once "./db.inc.php";
require_once "./processing.inc.php";
require_once "./webui.inc.php";
initDb();


testIfApiIsSet();

//If barcodes are passed through CLI or GET, process them and do not do anything else
if (isset($argv[1])) {
    processNewBarcode(sanitizeString($argv[1], true));
    die;
}
if (isset($_GET["add"])) {
    processNewBarcode(sanitizeString($_GET["add"], true));
    if (!isset($_GET["showui"])) {
       die("OK");
    }
    //Hide get
    header("Location: " . $_SERVER["PHP_SELF"]);
}

if (isset($_GET["delete"])) {
   switch ($_GET["delete"]) {
    case "known":
    $db->exec("DELETE FROM Barcodes WHERE name IS NOT 'N/A'");
        break;
    case "unknown":
    $db->exec("DELETE FROM Barcodes WHERE name='N/A'");
        break;
    case "log":
    $db->exec("DELETE FROM BarcodeLogs");
        break;
}
    //Hide get
    header("Location: " . $_SERVER["PHP_SELF"]);
    die();
}




// If a button was pressed, we are processing everything here.
// Only one row can be processed at a time
processButtons();


printHeader();

$barcodes =  getStoredBarcodes();
if (sizeof($barcodes['known'])>0 || sizeof($barcodes['unknown'])>0) {
$productinfo = getProductInfo();
}

echo generateTable($barcodes, true);

?>
 </div>
            </div>
            <button class="mdl-button mdl-js-button mdl-js-ripple-effect mdl-button--icon" id="btn1">
              <i class="material-icons">more_vert</i>
            </button>
            <ul class="mdl-menu mdl-js-menu mdl-menu--bottom-right" for="btn1">
              <li class="mdl-menu__item" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF'];?>?delete=known'">Delete all</li>
            </ul>
          </section>
       <section class="section--center mdl-grid mdl-grid--no-spacing mdl-shadow--2dp">
            <div class="mdl-card mdl-cell mdl-cell--12-col">
              <div class="mdl-card__supporting-text">
                <h4>Unknown Barcodes</h4>
                <?php echo generateTable($barcodes, false); ?>
              </div>
            </div>
            <button class="mdl-button mdl-js-button mdl-js-ripple-effect mdl-button--icon" id="btn2">
              <i class="material-icons">more_vert</i>
            </button>
            <ul class="mdl-menu mdl-js-menu mdl-menu--bottom-right" for="btn2">
              <li class="mdl-menu__item" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF'];?>?delete=unknown'">Delete all</li>
            </ul>
          </section>
          <section class="section--center mdl-grid mdl-grid--no-spacing mdl-shadow--2dp">
            <div class="mdl-card mdl-cell mdl-cell--12-col">
              <div class="mdl-card__supporting-text">
                <h4>Processed Barcodes</h4>
               <?php printLog(); ?>
              </div>
            </div>
            <button class="mdl-button mdl-js-button mdl-js-ripple-effect mdl-button--icon" id="btn3">
              <i class="material-icons">more_vert</i>
            </button>
            <ul class="mdl-menu mdl-js-menu mdl-menu--bottom-right" for="btn3">
              <li class="mdl-menu__item" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF'];?>?delete=log'">Clear log</li>
            </ul>

<?php printFooter();?>
