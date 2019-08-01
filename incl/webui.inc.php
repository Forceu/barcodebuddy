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


require_once __DIR__ . "/webui.tables.inc.php";


//Check if a button on the web ui was pressed and process
function processButtons() {
    global $db;
    
    if (isset($_GET["delete"])) {
        deleteAll($_GET["delete"]);
        //Hide get
        header("Location: " . $_SERVER["PHP_SELF"]);
        die();
    }
    
    if (isset($_POST["button_delete"])) {
        $id = $_POST["button_delete"];
        checkIfNumeric($id);
        deleteBarcode($id);
        //Hide POST, so we can refresh
        header("Location: " . $_SERVER["PHP_SELF"]);
        die();
    }
    
    
    if (isset($_POST["button_delete"])) {
        $id = $_POST["button_delete"];
        checkIfNumeric($id);
        deleteBarcode($id);
        //Hide POST, so we can refresh
        header("Location: " . $_SERVER["PHP_SELF"]);
        die();
    }
    
    if (isset($_POST["button_add_manual"])) {
        if (isset($_POST["newbarcodes"]) && strlen(trim($_POST["newbarcodes"])) > 0) {
            $barcodes = explode("\n", trim($_POST['newbarcodes']));
            foreach ($barcodes as $barcode) {
		$trimmedBarcode = trim(sanitizeString($barcode));
		if (strlen($trimmedBarcode)>0) {
                	processNewBarcode($trimmedBarcode, false);
		}
            }
        }
        
        //Hide POST, so we can refresh
        header("Location: " . $_SERVER["PHP_SELF"]);
        die();
    }
    
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
            $previousBarcodes = getProductInfo(sanitizeString($gidSelected))["barcode"];
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
        //Hide POST, so we can refresh
        header("Location: " . $_SERVER["PHP_SELF"]);
        die();
    }
}



function printHeader($isMain=false, $isSettings=false) {
if ($isMain) {
    $folder="./";
} else {
    $folder="../";
}
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
    <link rel="stylesheet" href="'.$folder.'styles.css">

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
    #save-settings {
      position: fixed;
      display: block;
      right: 0;
      bottom: 0;
      margin-right: 60px;
      margin-bottom: 60px;
      z-index: 900;
}
.mdl-mini-footer {

position: absolute !important;
bottom: 0 !important;
left: 0 !important;
right: 0 !important;
    }

/* The Modal (background) */
.modal {
  display: none; /* Hidden by default */
  position: fixed; /* Stay in place */
  z-index: 1; /* Sit on top */
  padding-top: 100px; /* Location of the box */
  left: 0;
  top: 0;
  width: 100%; /* Full width */
  height: 100%; /* Full height */
  overflow: auto; /* Enable scroll if needed */
  background-color: rgb(0,0,0); /* Fallback color */
  background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
}

/* Modal Content */
.modal-content {
  background-color: #fefefe;
  margin: auto;
  padding: 20px;
  border: 1px solid #888;
  width: 80%;
}

/* The Close Button */
.close {
  color: #aaaaaa;
  float: right;
  font-size: 28px;
  font-weight: bold;
}

.close:hover,
.close:focus {
  color: #000;
  text-decoration: none;
  cursor: pointer;
}
    </style>

  </head>

 <body class="mdl-demo mdl-color--grey-100 mdl-color-text--grey-700 mdl-base">

<div class="mdl-layout mdl-js-layout mdl-layout--fixed-header">
  <header class="mdl-layout__header">
    <div class="mdl-layout__header-row">
      <!-- Title -->
      <span class="mdl-layout-title">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a style="color: white; text-decoration: none;" href="'.$folder.'index.php">Barcode Buddy</a></span>
      <!-- Add spacer, to align navigation to the right -->
      <div class="mdl-layout-spacer"></div>';
      if (USE_WEBSOCKET) {
      echo '      <nav class="mdl-navigation mdl-layout--always">
        <a class="mdl-navigation__link" target="_blank" href="./screen.php">Screen</a>
      </nav>';
      }
  echo'  </div>
  </header>
  <div class="mdl-layout__drawer">
    <span class="mdl-layout-title">Menu</span>
    <nav class="mdl-navigation">
      <a class="mdl-navigation__link" href="'.$folder.'index.php">Overview</a>
      <a class="mdl-navigation__link" href="'.$folder.'menu/settings.php">Settings</a>
   <!--   <a class="mdl-navigation__link" href="'.$folder.'menu/quantities.php">Quantities</a> -->
      <a class="mdl-navigation__link" href="'.$folder.'menu/chores.php">Chores</a>
      <a class="mdl-navigation__link" href="'.$folder.'menu/tags.php">Tags</a>
    </nav>
  </div>';
}


function printFooter($isMain=false, $isSettings=false) {
    global $WEBSOCKET_PROXY_URL;
    echo '<footer class="mdl-mini-footer">
  <div class="mdl-mini-footer__left-section">
    <div class="mdl-logo">Barcode Buddy</div>
    <ul class="mdl-mini-footer__link-list">
              <li><a href="https://github.com/Forceu/barcodebuddy/">Help</a></li>
              <li><a href="https://github.com/Forceu/barcodebuddy/">Source Code</a></li>
              <li><a href="https://github.com/Forceu/barcodebuddy/blob/master/LICENSE">License</a></li>
              <li>by Marc Ole Bulling</li>
    </ul>
  </div>
</footer>
      </main></div>';
if ($isMain) {
echo '<div id="myModal" class="modal">

  <!-- Modal content -->
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2>Add barcode</h2>

Enter your barcodes below, one each line.&nbsp;<br><br>
<form name="form" method="post" action="' . $_SERVER['PHP_SELF'] . '" >
<textarea name="newbarcodes" id="newbarcodes" class="mdl-textfield__input" rows="15"></textarea>
<span style="font-size: 9px;">It is recommended to use a script that grabs the barcode scanner input, instead of doing it manually. See the <a href="https://github.com/Forceu/barcodebuddy" rel="noopener noreferrer" target="_blank">project website</a> on how to do this.</span><br><br><br>


<button  class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-color--accent mdl-color-text--accent-contrast" name="button_add_manual" type="submit" value="Add">Add</button>​
</form>
  </div>
</div>
 <button id="add-barcode" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-color--accent mdl-color-text--accent-contrast">Add barcode</button> ';
} else {
if ($isSettings) {
echo '<button id="save-settings" onclick="document.getElementById(\'settingsform\').submit();" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-color--accent mdl-color-text--accent-contrast">Save</button>';
} else {
echo '</div>';
}
}
echo '<script src="https://code.getmdl.io/1.3.0/material.min.js"></script>
<script>

function enableButton(idSelect, idButtonAdd, idButtonConsume)
{

    var oSelect = document.getElementById(idSelect);
    var oButtonAdd = document.getElementById(idButtonAdd);
    var oButtonConsume = document.getElementById(idButtonConsume);
    oButtonAdd.disabled = oSelect.value == "0";
    oButtonConsume.disabled = oSelect.value == "0";
}

';
if ($isMain) {
echo '
var modal = document.getElementById("myModal");
var btn = document.getElementById("add-barcode");
var span = document.getElementsByClassName("close")[0];
btn.onclick = function() {
  modal.style.display = "block";
document.getElementById("newbarcodes").focus();
}
span.onclick = function() {
  modal.style.display = "none";
}
window.onclick = function(event) {
  if (event.target == modal) {
    modal.style.display = "none";
  }
}';
}
echo '
function enableButtonGen(buttonId, textId, previousInput)
{
    var text=document.getElementById(textId).value;
    document.getElementById(buttonId).disabled=(text===previousInput);
}

</script>';
    if (USE_WEBSOCKET) {
        echo '<script>
      var ws = new WebSocket(';
        if (!USE_SSL_PROXY) {
            echo "'ws://" . $_SERVER["SERVER_NAME"] . ":" . WEBSOCKET_PUBLIC_PORT . "/screen');";
        } else {
            echo "'" . $WEBSOCKET_PROXY_URL . "');";
        }
        echo ' 
      ws.onopen = function() {
      };
      ws.onclose = function() {
      };
      ws.onmessage = function(event) {
        window.location.reload(true); 
      };

    </script>';
    }
    echo '</body>
</html>';
}
;


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
