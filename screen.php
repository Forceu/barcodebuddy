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




require_once "./incl/config.php";
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

      #soundbuttondiv {
        position: fixed;
        bottom: 10px;
        right: 10px;
      }
      #title {
        font: bold 50pt arial;
        margin: auto;
        padding: 10px;
        text-align: center;
      }
      #subtitle {
        font: bold 20pt arial;
        margin: auto;
        padding: 10px;
        text-align: center;
      }
      .sound {
        background-color : #31B0D5;
        color: white;
        padding: 1em 2em;
        border-radius: 4px;
        border-color: #46b8da;
      }
	#muteimg {
	  height: 2em;
	  width: 2em;
	}
@media only screen and (orientation: portrait) and not (display-mode: fullscreen) {
        .sound {
          padding: 2em 4em;
	}
	#muteimg {
	  height: 3em;
	  width: 3em;
	}
}
    </style>

  </head>
  <body bgcolor="#f6ff94">
  <script src="./incl/nosleep.min.js"></script>
    
    <div id="title">Connecting...</div><br>
    <div id="subtitle">If you see this for more than a couple of seconds, please check if the websocket-server was started</div>

    <audio id="beep_success" muted="muted" src="incl/websocket/beep.ogg"  type="audio/ogg" preload="auto"></audio>
    <audio id="beep_nosuccess" muted="muted" src="incl/websocket/buzzer.ogg"  type="audio/ogg" preload="auto"></audio>
    <div id="soundbuttondiv">
<button class="sound" onclick="toggleSound()" id="soundbutton"><img id="muteimg" src="incl/img/mute.svg" alt="Toggle sound and wakelock"></button>
</div>
    <script>

      var noSleep = new NoSleep();
      var wakeLockEnabled = false;
      
     function toggleSound() {
        if (!wakeLockEnabled) {
          noSleep.enable();
          wakeLockEnabled = true;
	  document.getElementById('beep_success').muted=false;
	  document.getElementById('beep_nosuccess').muted=false;
	  document.documentElement.requestFullscreen();
	  document.getElementById("muteimg").src = "incl/img/unmute.svg";
        } else {
          noSleep.disable();
	  document.exitFullscreen();
          wakeLockEnabled = false;
	  document.getElementById('beep_success').muted=true;
	  document.getElementById('beep_nosuccess').muted=true;
	  document.getElementById("muteimg").src = "incl/img/mute.svg";
        }
      }

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
		document.getElementById('beep_success').play();
	    break;
	  case '1':
		document.body.style.backgroundColor = '#a2ff9b';
		document.getElementById('title').textContent = 'Barcode looked up';
		document.getElementById('subtitle').textContent = resultText;
		document.getElementById('beep_success').play();
	    break;
	  case '2':
		document.body.style.backgroundColor = '#eaff8a';
		document.getElementById('title').textContent = 'Unknown barcode';
		document.getElementById('subtitle').textContent = resultText;
		document.getElementById('beep_nosuccess').play();
	    break;
	}
      };

    </script> 
    
  </body>
</html>
