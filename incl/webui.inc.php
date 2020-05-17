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


require_once __DIR__ . "/configProcessing.inc.php";
require_once __DIR__ . "/uiEditor.inc.php";
require_once __DIR__ . "/config.inc.php";

const MENU_GENERIC = 0;
const MENU_MAIN = 1;
const MENU_SETUP = 2;
const MENU_SETTINGS = 3;
const MENU_ERROR = 4;
const MENU_LOGIN = 5;


class MenuItemLink {

    public $itemText;
    public $itemLink;
    public $itemId;

    function __construct() {
        $this->itemId = 'btn'.rand();
        return $this;
    }

    public function setText($text) {
        $this->itemText = $text;
        return $this;
    }

    public function setLink($link) {
        $this->itemLink = $link;
        return $this;
    }
    public function setId($id) {
        $this->itemId = $id;
        return $this;
    }
}


class WebUiGenerator {
    private $htmlOutput      = "";
    private $menu            = MENU_GENERIC;
    
    function __construct($menu) {
        $this->menu = $menu;
    }


    function addHtml($html) {
        $this->htmlOutput = $this->htmlOutput . $html;
    }
    function addScript($js) {
        $this->htmlOutput = $this->htmlOutput ."<script>".$js."</script>";
    }


    function printHtml() {
        echo $this->htmlOutput;
    }
    

    function addCard($title, $html, $links = null) {
        $this->addHtml('
        <section class="section--center mdl-grid--no-spacing mdl-grid mdl-shadow--2dp">
            <div class="mdl-card mdl-cell  mdl-cell--12-col">
              <div class="mdl-card__supporting-text" style="overflow-x: auto; ">
                <h4>' . $title . '</h4><br>
        ' . $html . '
        </div>
            </div>');
        if ($links != null) {
            $linkArray = array();
            if (!is_array($links))
                $linkArray[0] = $links;
            else
                $linkArray = $links;
            
                $id = $linkArray[0]->itemId;
                $this->addHtml('<button class="mdl-button mdl-js-button mdl-js-ripple-effect mdl-button--icon" id="' . $id . '">
                  <i class="material-icons">more_vert</i>
                </button>
                <ul class="mdl-menu mdl-js-menu mdl-menu--bottom-right" for="' . $id . '">');

            foreach ($linkArray as $link) {
                  $this->addHtml('<li class="mdl-menu__item" onclick="' . $link->itemLink . '">' . $link->itemText . '</li>');
            }
            $this->addHtml('</ul>');
        }
        $this->addHtml('</section>');
    }

    function addHeader($additionalHeader = null) {
        global $CONFIG;
        
        if ($this->menu == MENU_SETTINGS || $this->menu === MENU_GENERIC) {
            $folder = "../";
        } else {
            $folder = "./";
        }
        if ($this->menu == MENU_SETUP || $this->menu == MENU_ERROR) {
            $indexfile = "setup.php";
        } elseif ($this->menu == MENU_LOGIN) {
            $indexfile = "login.php";
        } else {
            $indexfile = "index.php";
          }
        $this->addHtml('<!doctype html>
    <html lang="en">
      <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
        <title>Barcode Buddy</title>

        <link rel="apple-touch-icon" sizes="57x57" href="'.$folder.'incl/img/favicon/apple-icon-57x57.png">
        <link rel="apple-touch-icon" sizes="60x60" href="'.$folder.'incl/img/favicon/apple-icon-60x60.png">
        <link rel="apple-touch-icon" sizes="72x72" href="'.$folder.'incl/img/favicon/apple-icon-72x72.png">
        <link rel="apple-touch-icon" sizes="76x76" href="'.$folder.'incl/img/favicon/apple-icon-76x76.png">
        <link rel="apple-touch-icon" sizes="114x114" href="'.$folder.'incl/img/favicon/apple-icon-114x114.png">
        <link rel="apple-touch-icon" sizes="120x120" href="'.$folder.'incl/img/favicon/apple-icon-120x120.png">
        <link rel="apple-touch-icon" sizes="144x144" href="'.$folder.'incl/img/favicon/apple-icon-144x144.png">
        <link rel="apple-touch-icon" sizes="152x152" href="'.$folder.'incl/img/favicon/apple-icon-152x152.png">
        <link rel="apple-touch-icon" sizes="180x180" href="'.$folder.'incl/img/favicon/apple-icon-180x180.png">
        <link rel="icon" type="image/png" sizes="192x192"  href="'.$folder.'incl/img/favicon/android-icon-192x192.png">
        <link rel="icon" type="image/png" sizes="32x32" href="'.$folder.'incl/img/favicon/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="96x96" href="'.$folder.'incl/img/favicon/favicon-96x96.png">
        <link rel="icon" type="image/png" sizes="16x16" href="'.$folder.'incl/img/favicon/favicon-16x16.png">
        <meta name="msapplication-TileImage" content="'.$folder.'incl/img/favicon/ms-icon-144x144.png">
        <meta name="msapplication-navbutton-color" content="#3f51b5">
        <meta name="msapplication-TileColor" content="#3f51b5">
        <meta name="apple-mobile-web-app-status-bar-style" content="#3f51b5">
        <meta name="theme-color" content="#3f51b5">


        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:regular,bold,italic,thin,light,bolditalic,black,medium&amp;lang=en">
        <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
        <link rel="stylesheet" href="' . $folder . 'incl/css/material.indigo-blue.min.css">
        <link rel="stylesheet" href="' . $folder . 'incl/css/main.css">');
        if ($additionalHeader != null) {
            $this->addHtml($additionalHeader);
        }

      $this->addHtml('</head>

     <body class="mdl-demo mdl-color--grey-100 mdl-color-text--grey-700 mdl-base">
     <script src="'. $folder . 'incl/js/scripts_top.js"></script>

    <div class="mdl-layout mdl-js-layout mdl-layout--fixed-header">
      <header class="mdl-layout__header">
        <div class="mdl-layout__header-row">
          <!-- Title -->
          <span class="mdl-layout-title">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a style="color: white; text-decoration: none;" href="' . $folder . $indexfile . '">Barcode Buddy</a></span>
          <!-- Add spacer, to align navigation to the right -->
          <div class="mdl-layout-spacer"></div>');
        if ($this->menu != MENU_SETUP && $this->menu != MENU_ERROR && $this->menu != MENU_LOGIN) {
            $this->addHtml('<nav class="mdl-navigation mdl-layout--always">');
            if (!$CONFIG->HIDE_LINK_GROCY)
                $this->addHtml('<a class="mdl-navigation__link" target="_blank" href="' . BBConfig::getInstance()["GROCY_BASE_URL"] . '">Grocy</a>');

            if (!$CONFIG->HIDE_LINK_SCREEN)
                $this->addHtml('<a class="mdl-navigation__link" target="_blank" href="' . $folder . 'screen.php">Screen</a>');
            $this->addHtml('</nav>');
        }
        $this->addHtml('  </div>
      </header>');
        if ($this->menu != MENU_SETUP && $this->menu != MENU_ERROR && $this->menu != MENU_LOGIN) {
            $this->addHtml('<div class="mdl-layout__drawer">
        <span class="mdl-layout-title">Menu</span>
        <nav class="mdl-navigation">
          <a class="mdl-navigation__link" href="' . $folder . 'index.php">Overview</a>
          <a class="mdl-navigation__link" href="' . $folder . 'menu/settings.php">Settings</a>
          <a class="mdl-navigation__link" href="' . $folder . 'menu/quantities.php">Quantities</a>
          <a class="mdl-navigation__link" href="' . $folder . 'menu/chores.php">Chores</a>
          <a class="mdl-navigation__link" href="' . $folder . 'menu/tags.php">Tags</a>
          <a class="mdl-navigation__link" href="' . $folder . 'menu/apimanagement.php">API</a>');
        if (!$CONFIG->DISABLE_AUTHENTICATION) {
            $this->addHtml('
             <a class="mdl-navigation__link" href="' . $folder . 'menu/admin.php">Admin</a>');
        }
        $this->addHtml('</nav>
      </div>');
        }
    $this->addHtml('<main class="mdl-layout__content" style="flex: 1 0 auto;">
      <div class="mdl-layout__tab-panel is-active" id="overview">');
    }

    function addFooter() {

        if ($this->menu == MENU_SETTINGS || $this->menu === MENU_GENERIC) {
            $folder = "../";
        } else {
            $folder = "./";
        }

        $this->addHtml(' <section class="section--footer mdl-grid">
          </section>
<div id="snackbar" class="mdl-js-snackbar mdl-snackbar">
  <div class="mdl-snackbar__text"></div>
  <button class="mdl-snackbar__action" type="button"></button>
</div>


<footer class="mdl-mini-footer">
      <div class="mdl-mini-footer__left-section">
        <div class="mdl-logo">Barcode Buddy </div>
        <ul class="mdl-mini-footer__link-list">
              <li><a href="https://barcodebuddy-documentation.readthedocs.io/en/latest/">Documentation</a></li>
              <li><a href="https://github.com/Forceu/barcodebuddy/">Source Code</a></li>
              <li><a href="https://github.com/Forceu/barcodebuddy/blob/master/LICENSE">License</a></li>
          <li>Version ' . BB_VERSION_READABLE . '</li>
              <li>by Marc Ole Bulling</li>
        </ul>
      </div>
    </footer>
          </div></main>');

        if ($this->menu == MENU_MAIN) {
            $this->addHtml('<div id="myModal" class="modal">

          <!-- Modal content -->
          <div class="modal-content">
            <span class="close">&times;</span>
            <div>
            <h2>Add barcode</h2>

        Enter your barcodes below, one each line.&nbsp;<br><br>
        <form name="form" onsubmit="disableSSE()" method="post" action="' . $_SERVER['PHP_SELF'] . '" >
        <textarea name="newbarcodes" id="newbarcodes" class="mdl-textfield__input" rows="15"></textarea>

        <br>

        <button  class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-color--accent mdl-color-text--accent-contrast" name="button_add_manual"  id="button_add_manual" type="submit" value="Add">Add</button>​

        <br><br>

        <span style="font-size: 9px;">It is recommended to use a script that grabs the barcode scanner input, instead of doing it manually. See the <a href="https://barcodebuddy-documentation.readthedocs.io/en/latest/usage.html#adding-barcodes-automatically" rel="noopener noreferrer" target="_blank">documentation</a> on how to do this.</span><br>

        </form>
          </div>
          </div>
        </div>
         <button id="add-barcode" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-color--accent mdl-color-text--accent-contrast">Add barcode</button> ');
        }
        if ($this->menu == MENU_SETTINGS) {
            $this->addHtml('<button id="save-settings" onclick="checkAndReturn()" class="mdl-button mdl-js-button mdl-button--raised mdl-js-ripple-effect mdl-color--accent mdl-color-text--accent-contrast">Save</button>');
        }
        $this->addHtml('</div><script src="'. $folder.'incl/js/material.min.js"></script><script src="'. $folder.'incl/js/scripts.js"></script>');

        if ($this->menu == MENU_MAIN) {
            $this->addHtml('<script> 

        var eventSource = null;

        function disableSSE() {
            if (eventSource!=null)
                eventSource.close();
        }

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
        }

        const delay = ms => new Promise(res => setTimeout(res, ms));

        const startWebsocket = async () => {
            /* waiting 1s in case barcode was added from ui */
        await delay(1000);

        eventSource = new EventSource("incl/sse/sse_data.php");
        eventSource.onmessage = function(event) {
            var resultJson = JSON.parse(event.data);
                var resultCode = resultJson.data.substring(0, 1);
                var resultText = resultJson.data.substring(1);  
                switch(resultCode) {
                    case \'0\':
                    case \'1\':
                    case \'2\':

                    var xhttp = new XMLHttpRequest();
                    xhttp.onreadystatechange = function() {
                      if (this.readyState == 4 && this.status == 200) {
                          var content = JSON.parse(this.responseText);
                          var card1 = document.getElementById("f1");
                          card1.innerHTML = content.f1;
                          var card2 = document.getElementById("f2");
                          card2.innerHTML = content.f2;
                          var card3 = document.getElementById("f3");
                          card3.innerHTML = content.f3;
                          if (content.f4 != "null")
                            location.reload();
                      }
                    };
                    xhttp.open("GET", "index.php?ajaxrefresh", true);
                    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                    xhttp.send();
                        break;
                      }
                  };
                };
                if(typeof(EventSource) !== "undefined")
                  startWebsocket();

        </script>');
        }
        $this->addHtml('</body>
    </html>');
    }

}

class TableGenerator {
    private $htmlOutput = "";
    
    function __construct($tableHeadItems) {
        $this->htmlOutput  = '<table class="mdl-data-table mdl-js-data-table mdl-cell">
                 <thead>
                    <tr>';
        foreach ($tableHeadItems as $item) {
                $this->htmlOutput = $this->htmlOutput . '<th class="mdl-data-table__cell--non-numeric">' . $item . '</th>';
        }
        $this->htmlOutput = $this->htmlOutput . '    </tr>
                  </thead>
                  <tbody>';
    }
    
    
    function startRow() {
        $this->htmlOutput = $this->htmlOutput . '<tr>';
    }

    function addCell($html) {
            $this->htmlOutput = $this->htmlOutput . '<td class="mdl-data-table__cell--non-numeric">' . $html . '</td>';
    }

    function endRow() {
        $this->htmlOutput = $this->htmlOutput . '</tr>';
    }
    
    function getHtml() {
        return $this->htmlOutput . '</tbody></table>';
    }
    
}


function hideGetPostParameters() {
  header("Location: " . $_SERVER["PHP_SELF"]);
  die();
}


?>
