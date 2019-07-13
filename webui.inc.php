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
 * functions for web ui
 * 
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 *
 */


//Generate the table with barcodes
function generateTable($barcodes, $isKnown) {
    if ($isKnown) {
        if (sizeof($barcodes['known']) == 0) {
            return "No known barcodes yet.";
        } else {
            $returnString = '<form name="form" method="post" action="' . $_SERVER['PHP_SELF'] . '" >
                <table class="mdl-data-table mdl-js-data-table mdl-cell " >
                 <thead>
                    <tr>
                      <th class="mdl-data-table__cell--non-numeric">Name</th>
                      <th class="mdl-data-table__cell--non-numeric">Barcode</th>
                      <th>Quantity</th>
                      <th class="mdl-data-table__cell--non-numeric">Product</th>
                      <th class="mdl-data-table__cell--non-numeric">Action</th>
                      <th class="mdl-data-table__cell--non-numeric">Tags</th>
                      <th class="mdl-data-table__cell--non-numeric">Delete</th>
                    </tr>
                  </thead>
                  <tbody>';
            
            $returnString = $returnString . generateTableRow($barcodes, true) . '</tbody>
                </table>
                </form>';
            return $returnString;
        }
    } else {
        if (sizeof($barcodes['unknown']) == 0) {
            return "No unknown barcodes yet.";
        } else {
            $returnString = '<form name="form" method="post" action="' . $_SERVER['PHP_SELF'] . '" >
                <table class="mdl-data-table mdl-js-data-table mdl-cell " >
                 <thead>
                    <tr>
                      <th class="mdl-data-table__cell--non-numeric">Barcode</th>
                      <th class="mdl-data-table__cell--non-numeric">Look up</th>
                      <th>Quantity</th>
                      <th class="mdl-data-table__cell--non-numeric">Product</th>
                      <th class="mdl-data-table__cell--non-numeric">Action</th>
                      <th class="mdl-data-table__cell--non-numeric">Delete</th>
                    </tr>
                  </thead>
                  <tbody>';
            
            $returnString = $returnString . generateTableRow($barcodes, false) . '</tbody>
                </table>
                </form>';
            return $returnString;
        }
    }
}


//generate each row for the table
function generateTableRow($barcodes, $isKnown) {
    global $productinfo;
    $returnString = "";
    if ($isKnown) {
        foreach ($barcodes['known'] as $item) {
            $returnString = $returnString . '<tr>
        <td class="mdl-data-table__cell--non-numeric">' . $item['name'] . '</td>
              <td class="mdl-data-table__cell--non-numeric">' . $item['barcode'] . '</td>
              <td>' . $item['amount'] . '</td>
              <td class="mdl-data-table__cell--non-numeric"><select name="select_' . $item['id'] . '">' . printSelections($item['match'], $productinfo) . '</select></td>
        <td><button class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent" name="button_add" type="submit"  value="' . $item['id'] . '">Add</button> <button             class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent" name="button_consume" type="submit" value="' . $item['id'] . '">Consume</button> </td>
        <td>' . explodeWords($item['name'], $item['id']) . '</td>
        <td><button name="button_delete" type="submit" class="mdl-button mdl-js-button mdl-js-ripple-effect" value="' . $item['id'] . '">Delete</button></td></tr>';
        }
    } else {
        foreach ($barcodes['unknown'] as $item) {
            $returnString = $returnString . '<tr>
    <td class="mdl-data-table__cell--non-numeric">' . $item['barcode'] . '</td>
          <td class="mdl-data-table__cell--non-numeric"><a href="http://google.com/search?q=' . $item['barcode'] . '" target="_blank">Search for barcode</a></td>
          <td>' . $item['amount'] . '</td>
          <td class="mdl-data-table__cell--non-numeric"><select name="select_' . $item['id'] . '">' . printSelections($item['match'], $productinfo) . '</select></td>
        <td><button class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent" name="button_add" type="submit"  value="' . $item['id'] . '">Add</button> <button             class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-button--accent" name="button_consume" type="submit" value="' . $item['id'] . '">Consume</button> </td>
        <td><button name="button_delete" type="submit" class="mdl-button mdl-js-button mdl-js-ripple-effect" value="' . $item['id'] . '">Delete</button></td></tr>';
        }
    }
    return $returnString;
}


//If a button on the web ui was pressed
function processButtons() {
    global $db;
    if (isset($_POST["button_delete"])) {
        $id = $_POST["button_delete"];
        checkIfNumeric($id);
        deleteBarcode($id);
    } else {
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
            $res         = $db->query("SELECT * FROM Barcodes WHERE id='$id'");
            if ($gidSelected != 0 && ($row = $res->fetchArray())) {
                $barcode = sanitizeString($row["barcode"], true);
                $amount  = $row["amount"];
                checkIfNumeric($amount);
                foreach ($_POST["tags"][$id] as $tag) {
                    $db->exec("INSERT INTO Tags(tag, itemId) VALUES('" . sanitizeString($tag) . "', $gidSelected);");
                }
                $previousBarcodes = getProductInfo(sanitizeString($gidSelected)["barcode"]);
                if ($previousBarcodes == NULL) {
                    setBarcode($gidSelected, $barcode);
                } else {
                    setBarcode($gidSelected, $previousBarcodes . "," . $barcode);
                }
                deleteBarcode($id);
                if ($isConsume) {
                    consumeProduct($gidSelected, $amount);
                } else {
                    purchaseProduct($gidSelected, $amount);
                }
                
            }
        }
    }
    
    
}

function printFooter() {
    echo '
          </section>
          <section class="section--footer mdl-grid">
          </section>
        </div>
       
        <footer class="mdl-mega-footer">
          <div class="mdl-mega-footer--bottom-section">
            <div class="mdl-logo">
              More Information
            </div>
            <ul class="mdl-mega-footer--link-list">
              <li><a href="https://github.com/Forceu/barcodebuddy/">Help</a></li>
              <li><a href="https://github.com/Forceu/barcodebuddy/">Source Code</a></li>
              <li><a href="https://github.com/Forceu/barcodebuddy/blob/master/LICENSE">License</a></li>
              <li>by Marc Ole Bulling</li>
            </ul>
          </div>
        </footer>
      </main>
    </div>
 <!-- Maybe use in future <a href="#" target="_blank" id="add-barcode" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-color--accent mdl-color-text--accent-contrast">Add barcode</a> -->
    <script src="https://code.getmdl.io/1.3.0/material.min.js"></script>
  </body>
</html>';
}
;

function printHeader() {
    echo '<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <title>Barcode Buddy</title>

    <!-- Add to homescreen for Chrome on Android -->
<!--    <meta name="mobile-web-app-capable" content="yes">
    <link rel="icon" sizes="192x192" href="images/android-desktop.png"> -->

    <!-- Add to homescreen for Safari on iOS -->
<!--    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="Material Design Lite">
    <link rel="apple-touch-icon-precomposed" href="images/ios-desktop.png">

    <link rel="shortcut icon" href="images/favicon.png"> -->

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:regular,bold,italic,thin,light,bolditalic,black,medium&amp;lang=en">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://code.getmdl.io/1.3.0/material.indigo-blue.min.css">
    <link rel="stylesheet" href="styles.css">

    <style>
    #add-barcode {
      position: fixed;
      display: block;
      right: 0;
      bottom: 0;
      margin-right: 40px;
      margin-bottom: 40px;
      z-index: 900;
    }
    </style>

  </head>

 <body class="mdl-demo mdl-color--grey-100 mdl-color-text--grey-700 mdl-base">
 <div class="mdl-layout mdl-js-layout mdl-layout--fixed-header">
      <header class="mdl-layout__header mdl-layout__header--scroll mdl-color--primary">
        <div class="mdl-layout--large-screen-only mdl-layout__header-row">
          <h2>Barcode Buddy</h2>
        </div>
       <!-- maybe use later
        <div class="mdl-layout__tab-bar mdl-js-ripple-effect mdl-color--primary-dark">
          <a href="#overview" class="mdl-layout__tab is-active">Overview</a>
          <a href="#features" class="mdl-layout__tab">Features</a>
          <a href="#features" class="mdl-layout__tab">Details</a>
          <a href="#features" class="mdl-layout__tab">Technology</a>
          <a href="#features" class="mdl-layout__tab">FAQ</a>
          <button class="mdl-button mdl-js-button mdl-button--fab mdl-js-ripple-effect mdl-button--colored mdl-shadow--4dp mdl-color--accent" id="add">
            <i class="material-icons" role="presentation">add</i>
            <span class="visuallyhidden">Add</span>
          </button>
        </div> -->
      </header>
      <main class="mdl-layout__content">
        <div class="mdl-layout__tab-panel is-active" id="overview">
       <section class="section--center mdl-grid--no-spacing mdl-grid mdl-shadow--2dp">
            <div class="mdl-card mdl-cell  mdl-cell--12-col">
              <div class="mdl-card__supporting-text" style="overflow-x: auto; ">
                <h4>New Barcodes</h4><br>';
}

//outputs stored logs to the textarea
function printLog() {
    $logs = getLogs();
    if (sizeof($logs) == 0) {
        echo "No barcodes processed yet.";
    } else {
        echo '<textarea readonly class="mdl-textfield__input" rows="15">';
        foreach ($logs as $log) {
            echo $log . "\r\n";
        }
        echo '</textarea>';
    }
}

?>
