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




require_once __DIR__ . "/incl/config.php";
require_once __DIR__ . "/incl/db.inc.php";

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
      #mode {
        font: bold 10pt arial;
        margin: auto;
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
  <script src="./incl/he.js"></script>
    
    <div id="title">Connecting...</div><br>
    <div id="mode"></div><br><br><br>
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
	  <?php if ($BBCONFIG["WS_FULLSCREEN"]) { echo " document.documentElement.requestFullscreen();"; }?>
	  document.getElementById("muteimg").src = "incl/img/unmute.svg";
        } else {
          noSleep.disable();
	  <?php if ($BBCONFIG["WS_FULLSCREEN"]) { echo " document.exitFullscreen();"; } ?>
          wakeLockEnabled = false;
	  document.getElementById('beep_success').muted=true;
	  document.getElementById('beep_nosuccess').muted=true;
	  document.getElementById("muteimg").src = "incl/img/mute.svg";
        }
      }

      if(typeof(EventSource) !== "undefined") {
  var source = new EventSource("incl/sse/sse_data.php");


document.body.style.backgroundColor = '#b9ffad';
document.getElementById('title').textContent = 'Connected';
document.getElementById('subtitle').textContent = 'Waiting for barcode...';

  source.onmessage = function(event) {
       var resultJson = JSON.parse(event.data);
            var resultCode = resultJson.data.substring(0, 1);
            var resultText = resultJson.data.substring(1);  
      switch(resultCode) {
        case '0':
        document.body.style.backgroundColor = '#47ac3f';
        document.getElementById('title').textContent = 'Scan success';
        document.getElementById('subtitle').textContent = he.decode(resultText);
        document.getElementById('beep_success').play();
          break;
        case '1':
        document.body.style.backgroundColor = '#a2ff9b';
        document.getElementById('title').textContent = 'Barcode looked up';
        document.getElementById('subtitle').textContent = he.decode(resultText);
        document.getElementById('beep_success').play();
          break;
        case '2':
        document.body.style.backgroundColor = '#eaff8a';
        document.getElementById('title').textContent = 'Unknown barcode';
        document.getElementById('subtitle').textContent = resultText;
        document.getElementById('beep_nosuccess').play();
          break;
        case '4':
        document.getElementById('mode').textContent = 'Current Mode: '+resultText;
          break;
        case 'E':
        document.body.style.backgroundColor = '#f9868b';
        document.getElementById('title').textContent = 'Error';
        document.getElementById('subtitle').textContent = resultText;
          break;
      }
  };
} else {
        document.body.style.backgroundColor = '#f9868b';
        document.getElementById('title').textContent = 'Disconnected';
        document.getElementById('subtitle').textContent = 'Sorry, your browser does not support server-sent events';
}
    </script> 
    
  </body>
</html>
