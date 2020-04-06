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
      body, html{
          padding:0;
          margin:0;
          position:relative;
          height:100%
      }
      .header {
          width:100%;
          background:#ccc;
          padding:10px;
          box-sizing:border-box;
          text-transform: lowercase;
      }
      .content{
          background:#eee;
          width:100%;
          padding:10px;
          height:100%;
          box-sizing:border-box;
          padding:10px;
          text-align: center;
          align-content: center
      }
      .hdr-left {
        text-align: center;
        padding-left: 10px; 
      }
      .hdr-right {
        float: right;
        width: 30%;
        text-align: right;
        padding-right: 10px
      }

      #soundbuttondiv {
        position: fixed;
        bottom: 10px;
        right: 10px;
      }
      .h1 {
        font: bold 50pt arial;
        margin: auto;
        text-align: center;
      }
      .h2 {
        font: bold 40pt arial;
        margin: auto;
        padding: 10px;
        text-align: center;
      }
      .h3 {
        font: bold 30pt arial;
        margin: auto;
        padding: 10px;
        text-align: center;
      }
      .h4 {
        font: bold 15pt arial;
        margin: auto;
        padding: 6px;
      }
      .h5 {
        font: bold 10pt arial;
        margin: auto;
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
  <body>
  <script src="./incl/js/nosleep.min.js"></script>
  <script src="./incl/js/he.js"></script>

  <div id="header" class="header">
    <span class="hdr-right h4">
      Grocy Status: <span id="grocy-sse">Connecting...</span><br>
    </span>
      <span id="mode" class="h1 hdr-left"></span>
    </span>
  </div>
    <div id="content" class="content">
       <p id="scan-result" class="h2">If you see this for more than a couple of seconds, please check if the websocket server has been started and is available</p>
      <div id="log">
          <p id="event" class="h3"></p><br>
          <div id="previous-events">
          <p class="h4 p-t10"> previous scans: </p>
          <span id="log-entries" class="h5"></span>
        </div>
      </div>
  </div>


    <audio id="beep_success" muted="muted" src="incl/websocket/beep.ogg"  type="audio/ogg" preload="auto"></audio>
    <audio id="beep_nosuccess" muted="muted" src="incl/websocket/buzzer.ogg"  type="audio/ogg" preload="auto"></audio>
  <div id="soundbuttondiv">
    <button class="sound" onclick="toggleSound()" id="soundbutton"><img id="muteimg" src="incl/img/mute.svg" alt="Toggle sound and wakelock"></button>
  </div>
    
    <script>

      var noSleep          = new NoSleep();
      var wakeLockEnabled  = false;
      var isFirstStart     = true;
      
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

  var currentScanId = 0;

  async function resetScan(scanId) {
    await sleep(3000);
    if (currentScanId == scanId) {
      content.style.backgroundColor = '#eee';
      document.getElementById('scan-result').textContent = 'waiting for barcode...';
      document.getElementById('event').textContent = '';
    }
  };

  function sleep(ms) {
      return new Promise(resolve => setTimeout(resolve, ms));
  };

  function resultScan(color, message, text, sound) {
    content.style.backgroundColor = color;
    document.getElementById('event').textContent = message;
    document.getElementById('scan-result').textContent = text;
    document.getElementById(sound).play();
    document.getElementById('log-entries').innerText = '\r\n' + text + document.getElementById('log-entries').innerText;
    currentScanId++;
    resetScan(currentScanId);

  };

  source.onopen = function() {
    if (isFirstStart) {
      isFirstStart=false;
      document.getElementById('grocy-sse').textContent = 'Connected';
      document.getElementById('scan-result').textContent = 'waiting for barcode...';
      var http = new XMLHttpRequest();
      http.open("GET", "incl/sse/sse_data.php?getState");
      http.send();
    }
  };

  source.onmessage = function(event) {
       var resultJson = JSON.parse(event.data);
            var resultCode = resultJson.data.substring(0, 1);
            var resultText = resultJson.data.substring(1);  
      switch(resultCode) {
        case '0':
        resultScan("#33a532", "", he.decode(resultText), "beep_success");
          break;
        case '1':
        resultScan("#33a532", "Barcode Looked Up", he.decode(resultText), "beep_success");
          break;
        case '2':
        resultScan("#F7B500", "Unknown Barcode", resultText, "beep_nosuccess");
          break;
        case '4':
        document.getElementById('mode').textContent = resultText;
          break;
        case 'E':
          content.style.backgroundColor = '#CC0605';
          document.getElementById('grocy-sse').textContent = 'disconnected';
          document.getElementById('scan-result').style.display = 'none'
          document.getElementById('previous-events').style.display = 'none'
          document.getElementById('event').setAttribute('style', 'white-space: pre;');
          document.getElementById('event').textContent = "\r\n\r\n"+resultText;
          break;
      }
  };
} else {
        content.style.backgroundColor = '#f9868b';
        document.getElementById('grocy-sse').textContent = 'Disconnected';
        document.getElementById('event').textContent = 'Sorry, your browser does not support server-sent events';
}
    </script> 
    
  </body>
</html>
