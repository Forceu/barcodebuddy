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
 * A screen to supervise barcode scanning. Websockets need to be enabled!
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 *
 * TODO: Sound is not played on Android devices
 */




require_once "./config.php";
require_once "./incl/db.inc.php";

if (!$BBCONFIG["WS_USE"]) {
   die("Please enable websockets in the settings first!");
}
?>

<!DOCTYPE html>
<html>
  <head>
    <title>Barcode Buddy Screen</title>
    <style>
      #title {
        font: bold 50px arial;
        margin: auto;
        padding: 10px;
        text-align: center;
      }
      #subtitle {
        font: bold 20px arial;
        margin: auto;
        padding: 10px;
        text-align: center;
      }
    </style>
  </head>
  <body bgcolor="#f6ff94">
    
    <div id="title">Connecting...</div><br>
    <div id="subtitle">If you see this for more than a couple of seconds, please check if the websocket-server was started</div>
    <div id="mute" class="btn"></div>

    <audio id="beep_success" muted="muted" src="incl/websocket/beep.ogg"  type="audio/ogg" preload="auto"></audio>
    <audio id="beep_nosuccess" muted="muted" src="incl/websocket/buzzer.ogg"  type="audio/ogg" preload="auto"></audio>
    
    <script>
      var ws = new WebSocket( <?php
	 if (!$BBCONFIG["WS_SSL_USE"]) {
             echo "'ws://".$_SERVER["SERVER_NAME"].":".$BBCONFIG["WS_PORT_EXT"]."/screen');";
	 } else {
             echo "'".$BBCONFIG["WS_SSL_URL"]."');";
	 }
      ?> 
      var beep_success = new Audio('beep.ogg');
      ws.onopen = function() {
        document.body.style.backgroundColor = '#b9ffad';
        document.getElementById('title').textContent = 'Connected';
        document.getElementById('subtitle').textContent = 'Waiting for barcode...';
      };
      ws.onclose = function() {
        document.body.style.backgroundColor = '#f9868b';
        document.getElementById('title').textContent = 'Disconnected';
        document.getElementById('subtitle').textContent = 'Please check connection';
      };
      ws.onmessage = function(event) {
	var resultJson = JSON.parse(event.data);
        var resultCode = resultJson.data.substring(0, 1);
        var resultText = resultJson.data.substring(1);
	switch(resultCode) {
	  case '0':
		document.body.style.backgroundColor = '#47ac3f';
		document.getElementById('title').textContent = 'Scan success';
		document.getElementById('subtitle').textContent = resultText;
		document.getElementById('beep_success').muted=false;
		document.getElementById('beep_success').play();
	    break;
	  case '1':
		document.body.style.backgroundColor = '#a2ff9b';
		document.getElementById('title').textContent = 'Barcode looked up';
		document.getElementById('subtitle').textContent = resultText;
		document.getElementById('beep_success').muted=false;
		document.getElementById('beep_success').play();
	    break;
	  case '2':
		document.body.style.backgroundColor = '#eaff8a';
		document.getElementById('title').textContent = 'Unknown barcode';
		document.getElementById('subtitle').textContent = resultText;
		document.getElementById('beep_nosuccess').muted=false;
		document.getElementById('beep_nosuccess').play();
	    break;
	}
      };

    </script>
    
  </body>
</html>
