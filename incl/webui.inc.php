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

const MENU_GENERIC = 0;
const MENU_MAIN = 1;
const MENU_SETUP = 2;
const MENU_SETTINGS = 3;



class WebUiGenerator {
    private $htmlOutput = "";
    private $menu = MENU_GENERIC;
    
    function __construct($menu) {
        $this->menu = $menu;
    }


    function addHtml($html) {
        $this->htmlOutput = $this->htmlOutput .$html;
    }


    function printHtml() {
        echo $this->htmlOutput;
    }
    

    function addCard($title, $html, $linkText=null, $linkUrl=null) {
        $this->htmlOutput = $this->htmlOutput.'
	    <section class="section--center mdl-grid--no-spacing mdl-grid mdl-shadow--2dp">
            <div class="mdl-card mdl-cell  mdl-cell--12-col">
              <div class="mdl-card__supporting-text" style="overflow-x: auto; ">
                <h4>'.$title.'</h4><br>
		'.$html.'
		</div>
            </div>';
	 if ($linkText !=null &&  $linkUrl!=null) {
		$id=rand();
 	   $this->htmlOutput = $this->htmlOutput.'<button class="mdl-button mdl-js-button mdl-js-ripple-effect mdl-button--icon" id="btn'.$id.'">
              <i class="material-icons">more_vert</i>
            </button>
            <ul class="mdl-menu mdl-js-menu mdl-menu--bottom-right" for="btn'.$id.'">
              <li class="mdl-menu__item" onclick="window.location.href=\''.$linkUrl.'\'">'.$linkText.'</li>
            </ul>';
	}
          $this->htmlOutput = $this->htmlOutput.'</section>';
    }

    function addHeader() {
        global $BBCONFIG;
        
        if ($this->menu == MENU_SETTINGS || $this->menu == MENU_GENERIC) {
            $folder = "../";
        } else {
            $folder = "./";
        }
        if ($this->menu == MENU_SETUP) {
            $indexfile = "setup.php";
        } else {
            $indexfile = "index.php";
        }
        $this->htmlOutput = $this->htmlOutput . '<!doctype html>
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
        <link rel="stylesheet" href="' . $folder . 'styles.css">

      </head>

     <body class="mdl-demo mdl-color--grey-100 mdl-color-text--grey-700 mdl-base">

    <div class="mdl-layout mdl-js-layout mdl-layout--fixed-header">
      <header class="mdl-layout__header">
        <div class="mdl-layout__header-row">
          <!-- Title -->
          <span class="mdl-layout-title">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a style="color: white; text-decoration: none;" href="' . $folder . $indexfile . '">Barcode Buddy</a></span>
          <!-- Add spacer, to align navigation to the right -->
          <div class="mdl-layout-spacer"></div>';
        if ($this->menu != MENU_SETUP) {
            $this->htmlOutput = $this->htmlOutput . '      <nav class="mdl-navigation mdl-layout--always"><a class="mdl-navigation__link" target="_blank" href="' . str_replace("api/", "", $BBCONFIG["GROCY_API_URL"]) . '">Grocy</a>';
            if ($BBCONFIG["WS_USE"]) {
                $this->htmlOutput = $this->htmlOutput . '<a class="mdl-navigation__link" target="_blank" href="' . $folder . 'screen.php">Screen</a>';
            }
            $this->htmlOutput = $this->htmlOutput . '</nav>';
        }
        $this->htmlOutput = $this->htmlOutput . '  </div>
      </header>';
        if ($this->menu != MENU_SETUP) {
            $this->htmlOutput = $this->htmlOutput . '<div class="mdl-layout__drawer">
        <span class="mdl-layout-title">Menu</span>
        <nav class="mdl-navigation">
          <a class="mdl-navigation__link" href="' . $folder . 'index.php">Overview</a>
          <a class="mdl-navigation__link" href="' . $folder . 'menu/settings.php">Settings</a>
    <!--      <a class="mdl-navigation__link" href="' . $folder . 'menu/quantities.php">Quantities</a> -->
          <a class="mdl-navigation__link" href="' . $folder . 'menu/chores.php">Chores</a>
          <a class="mdl-navigation__link" href="' . $folder . 'menu/tags.php">Tags</a>
        </nav>
      </div>';
        }
	$this->htmlOutput = $this->htmlOutput . '<main class="mdl-layout__content" style="flex: 1 0 auto;">
      <div class="mdl-layout__tab-panel is-active" id="overview">';
    }

    function addFooter() {
        global $BBCONFIG;

        $this->htmlOutput = $this->htmlOutput . ' <section class="section--footer mdl-grid">
          </section><footer class="mdl-mini-footer">
	  <div class="mdl-mini-footer__left-section">
	    <div class="mdl-logo">Barcode Buddy </div>
	    <ul class="mdl-mini-footer__link-list">
		      <li><a href="https://github.com/Forceu/barcodebuddy/">Help</a></li>
		      <li><a href="https://github.com/Forceu/barcodebuddy/">Source Code</a></li>
		      <li><a href="https://github.com/Forceu/barcodebuddy/blob/master/LICENSE">License</a></li>
		  <li>Version ' . BB_VERSION_READABLE . '</li>
		      <li>by Marc Ole Bulling</li>
	    </ul>
	  </div>
	</footer>
	      </div></main>';

        if ($this->menu == MENU_MAIN) {
            $this->htmlOutput = $this->htmlOutput . '<div id="myModal" class="modal">

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
        }
        if ($this->menu == MENU_SETTINGS) {
            $this->htmlOutput = $this->htmlOutput . '<button id="save-settings" onclick="checkAndReturn()" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-color--accent mdl-color-text--accent-contrast">Save</button>';
        }
        $this->htmlOutput = $this->htmlOutput . '</div><script src="https://code.getmdl.io/1.3.0/material.min.js"></script>
		<script>

		function enableButton(idSelect, idButtonAdd, idButtonConsume)
		{

		    var oSelect = document.getElementById(idSelect);
		    var oButtonAdd = document.getElementById(idButtonAdd);
		    var oButtonConsume = document.getElementById(idButtonConsume);
		    oButtonAdd.disabled = oSelect.value == "0";
		    oButtonConsume.disabled = oSelect.value == "0";
		}';
        
        if ($this->menu == MENU_SETTINGS) {
            $this->htmlOutput = $this->htmlOutput . 'function switchElements() {
	    var wsuse = document.getElementById("websocket_use");
	    var wspint = document.getElementById("websocket_port_internal");
	    var wspext = document.getElementById("websocket_port_external");
	    var wsssluse = document.getElementById("websocket_ssl_use");
	    var wssslurl = document.getElementById("websocket_ssl_url");

	      wspint.disabled = !wsuse.checked;
	      wspint.disabled = !wsuse.checked;
	      wspext.disabled = !(wsuse.checked && !wsssluse.checked);
	      wsssluse.disabled = !wsuse.checked;
	      wssslurl.disabled = !(wsuse.checked && wsssluse.checked);

	   if (wspint.disabled) {
	      wspint.parentElement.MaterialTextfield.disable()
	   } else {
	      wspint.parentElement.MaterialTextfield.enable()
	   }
	   if (wspext.disabled) {
	      wspext.parentElement.MaterialTextfield.disable()
	   } else {
	      wspext.parentElement.MaterialTextfield.enable()
	   }
	   if (wsssluse.disabled) {
	      wsssluse.parentElement.MaterialCheckbox.disable()
	   } else {
	      wsssluse.parentElement.MaterialCheckbox.enable()
	   }
	   if (wssslurl.disabled) {
	      wssslurl.parentElement.MaterialTextfield.disable()
	   } else {
	      wssslurl.parentElement.MaterialTextfield.enable()
	   }
	}

	switchElements();

	function checkAndReturn() {
	    var wspint = document.getElementById("websocket_port_internal").value;
	    var wspint = document.getElementById("websocket_port_internal").value;
	    var crevert = document.getElementById("general_revert_min").value;

	    if (Number.isInteger(+wspint) && Number.isInteger(+wspint) && Number.isInteger(+crevert)) {
	       document.getElementById(\'settingsform\').submit();
	    } else {
	       alert("Please only enter digits for port and minutes.");
	    } }';           
        }

        if ($this->menu == MENU_MAIN) {
            $this->htmlOutput = $this->htmlOutput . '
		var modal = document.getElementById("myModal");
		var btn = document.getElementById("add-barcode");
		var span = document.getElementsByClassName("close")[0];
		btn.onclick = function() {
		  modal.style.display = "block";
		  btn.style.display = "none";
		document.getElementById("newbarcodes").focus();
		}
		span.onclick = function() {
		  modal.style.display = "none";
		  btn.style.display = "block";
		}
		window.onclick = function(event) {
		  if (event.target == modal) {
		    modal.style.display = "none";
		    btn.style.display = "block";
		  }
		}';
        }
        $this->htmlOutput = $this->htmlOutput . '
		function enableButtonGen(buttonId, textId, previousInput)
		{
		    var text=document.getElementById(textId).value;
		    document.getElementById(buttonId).disabled=(text===previousInput);
		}
		function openNewTab(url, barcode) {
		    var win = window.open(url,	"New Grocy product");
		    var timer = setInterval(function() {
			if (win.closed) {
			    clearInterval(timer);
			    window.location = "index.php?refreshbarcode="+barcode;
			}
		    }, 500);
		}
		</script>';
        if ($BBCONFIG["WS_USE"]) {
            $this->htmlOutput = $this->htmlOutput . '<script>
      		var ws = new WebSocket(';
            if (!$BBCONFIG["WS_SSL_USE"]) {
                $this->htmlOutput = $this->htmlOutput . "'ws://" . $_SERVER["SERVER_NAME"] . ":" . $BBCONFIG["WS_PORT_EXT"] . "/screen');";
            } else {
                $this->htmlOutput = $this->htmlOutput . "'" . $BBCONFIG["WS_SSL_URL"] . "');";
            }
            $this->htmlOutput = $this->htmlOutput . ' 
	      ws.onopen = function() {
	      };
	      ws.onclose = function() {
	      };
	      ws.onmessage = function(event) {
		window.location.reload(true); 
	      };

	    </script>';
        }
        $this->htmlOutput = $this->htmlOutput . '</body>
	</html>';
    }

}



function hideGetPostParameters() {
  header("Location: " . $_SERVER["PHP_SELF"]);
  die();
}



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

?>
