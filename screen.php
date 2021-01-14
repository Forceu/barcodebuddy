<?php
/**
 * Barcode Buddy for Grocy
 *
 * PHP version 7
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 *  A screen to supervise barcode scanning.
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */

require_once __DIR__ . "/incl/configProcessing.inc.php";
require_once __DIR__ . "/incl/config.inc.php";
require_once __DIR__ . "/incl/redis.inc.php";

$CONFIG->checkIfAuthenticated(true);


?>
<!DOCTYPE html>
<html>
<head>


    <link rel="apple-touch-icon" sizes="57x57" href="./incl/img/favicon/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="./incl/img/favicon/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="./incl/img/favicon/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="./incl/img/favicon/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="./incl/img/favicon/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="./incl/img/favicon/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="./incl/img/favicon/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="./incl/img/favicon/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="./incl/img/favicon/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192" href="./incl/img/favicon/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="./incl/img/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="./incl/img/favicon/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="./incl/img/favicon/favicon-16x16.png">
    <meta name="msapplication-TileImage" content="./incl/img/favicon/ms-icon-144x144.png">
    <meta name="msapplication-navbutton-color" content="#ccc">
    <meta name="msapplication-TileColor" content="#ccc">
    <meta name="apple-mobile-web-app-status-bar-style" content="#ccc">
    <meta name="theme-color" content="#ccc">


    <title>Barcode Buddy Screen</title>
    <style>
        body,
        html {
            padding: 0;
            margin: 0;
            position: relative;
            height: 100%
        }


        .main-container {
            height: 100%;
            display: flex;
            display: -webkit-flex;
            flex-direction: column;
            -webkit-flex-direction: column;
            -webkit-align-content: stretch;
            align-content: stretch;
        }

        .header {
            width: 100%;
            background: #ccc;
            padding: 10px;
            box-sizing: border-box;
            text-transform: lowercase;
            flex: 0 1 auto;
        }

        .content {
            background: #eee;
            width: 100%;
            padding: 10px;
            flex: 1 0 auto;
            box-sizing: border-box;
            padding: 10px;
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

        #backbuttondiv {
            position: fixed;
            bottom: 10px;
            left: 10px;
        }

        #selectbuttondiv {
            position: fixed;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
        }

        .h1 {
            font: bold 4em arial;
            margin: auto;
            text-align: center;
        }

        .h2 {
            font: bold 3em arial;
            margin: auto;
            padding: 10px;
            text-align: center;
        }

        .h3 {
            font: bold 2em arial;
            margin: auto;
            padding: 10px;
            text-align: center;
        }

        .h4 {
            font: bold 1.5em arial;
            margin: auto;
            padding: 6px;
        }

        .h5 {
            font: bold 1em arial;
            margin: auto;
            text-align: center;
        }

        .bottom-button {
            background-color: #31B0D5;
            color: white;
            padding: 1em 2em;
            border-radius: 4px;
            border-color: #46b8da;
        }

        .bottom-img {
            height: 2.5em;
            width: 2.5em;
        }

        @media only screen and (orientation: portrait)  not (display-mode: fullscreen) {
            .bottom-button {
                padding: 2em 4em;
            }

            .bottom-img {
                height: 3em;
                width: 3em;
            }
        }

        .overlay {
            height: 0;
            width: 100%;
            position: fixed;
            z-index: 1;
            bottom: 0;
            left: 0;
            background-color: rgb(0, 0, 0);
            background-color: rgba(0, 0, 0, 0.9);
            overflow-x: hidden;
            transition: 0.3s;
        }

        .overlay-content {
            position: relative;
            top: 25%;
            width: 100%;
            text-align: center;
            margin-top: 30px;
        }

        .overlay a {
            padding: 8px;
            text-decoration: none;
            font-size: 36px;
            color: #818181;
            display: block;
            transition: 0.2s;
        }

        .overlay a:hover, .overlay a:focus {
            color: #f1f1f1;
        }

        .overlay .closebtn {
            position: absolute;
            top: 20px;
            right: 45px;
            font-size: 60px;
        }

        @media screen and (max-height: 450px) {
            .overlay a {
                font-size: 20px
            }

            .overlay .closebtn {
                font-size: 40px;
                top: 15px;
                right: 35px;
            }
        }
    </style>

</head>
<body>
<script src="./incl/js/nosleep.min.js"></script>
<script src="./incl/js/he.js"></script>

<div class="main-container">
    <div id="header" class="header">
    <span class="hdr-right h4">
      Status: <span id="grocy-sse">Connecting...</span><br>
    </span>
        <span id="mode" class="h1 hdr-left"></span>
    </div>
    <div id="content" class="content">
        <p id="scan-result" class="h2">If you see this for more than a couple of seconds, please check if the websocket
            server has been started and is available</p>
        <div id="log">
            <p id="event" class="h3"></p><br>
            <div id="previous-events">
                <p class="h4 p-t10"> previous scans: </p>
                <span id="log-entries" class="h5"></span>
            </div>
        </div>
    </div>
</div>

<audio id="beep_success" muted="muted" src="incl/websocket/beep.ogg" type="audio/ogg" preload="auto"></audio>
<audio id="beep_nosuccess" muted="muted" src="incl/websocket/buzzer.ogg" type="audio/ogg" preload="auto"></audio>
<div id="soundbuttondiv">
    <button class="bottom-button" onclick="toggleSound()" id="soundbutton"><img class="bottom-img"
                                                                                src="incl/img/mute.svg"
                                                                                alt="Toggle sound and wakelock">
    </button>
</div>
<div id="backbuttondiv">
    <button class="bottom-button" onclick="goHome()" id="backbutton"><img class="bottom-img" src="incl/img/back.svg"
                                                                          alt="Go back to overview">
    </button>
</div>
<div id="selectbuttondiv">
    <button class="bottom-button" onclick="openNav()" id="selectbutton"><img class="bottom-img" src="incl/img/cart.svg"
                                                                             alt="Set mode">
    </button>
</div>


<div id="myNav" class="overlay">
    <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
    <div class="overlay-content">
        <a href="#" onclick="sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_P"] ?>')">Purchase</a>
        <a href="#" onclick="sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_C"] ?>')">Consume</a>
        <a href="#" onclick="sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_O"] ?>')">Open</a>
        <a href="#" onclick="sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_GS"] ?>')">Inventory</a>
        <a href="#" onclick="sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_AS"] ?>')">Add to shoppinglist</a>
        <a href="#" onclick="sendQuantity()">Set quantity</a>
        <a href="#" onclick="sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_CA"] ?>')">Consume All</a>
        <a href="#" onclick="sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_CS"] ?>')">Consume (spoiled)</a>
    </div>
</div>

<script>

    function openNav() {
        document.getElementById("myNav").style.height = "100%";
    }

    function closeNav() {
        document.getElementById("myNav").style.height = "0%";
    }

    function sendBarcode(barcode) {
        var xhttp = new XMLHttpRequest();
        xhttp.open("GET", "./api/action/scan?add=" + barcode, true);
        xhttp.send();
        closeNav();
    }

    function sendQuantity() {
        var q = prompt('Enter quantity', '1');
        sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_Q"] ?>' + q);
    }

    var noSleep = new NoSleep();
    var wakeLockEnabled = false;
    var isFirstStart = true;


    function goHome() {
        if (document.referrer === "") {
            window.location.href = './index.php'
        } else {
            window.close();
        }
    }

    function toggleSound() {
        if (!wakeLockEnabled) {
            noSleep.enable();
            wakeLockEnabled = true;
            document.getElementById('beep_success').muted = false;
            document.getElementById('beep_nosuccess').muted = false;
            <?php if (BBConfig::getInstance()["WS_FULLSCREEN"]) {
            echo " document.documentElement.requestFullscreen();";
        }?>
            document.getElementById("muteimg").src = "incl/img/unmute.svg";
        } else {
            noSleep.disable();
            <?php if (BBConfig::getInstance()["WS_FULLSCREEN"]) {
            echo " document.exitFullscreen();";
        } ?>
            wakeLockEnabled = false;
            document.getElementById('beep_success').muted = true;
            document.getElementById('beep_nosuccess').muted = true;
            document.getElementById("muteimg").src = "incl/img/mute.svg";
        }
    }


    function syncCache() {
        var xhttp = new XMLHttpRequest();
        xhttp.open("GET", "./cron.php", true);
        xhttp.send();
    }

    if (typeof (EventSource) !== "undefined") {
        syncCache()
        var source = new EventSource("incl/sse/sse_data.php");

        var currentScanId = 0;
        var connectFailCounter = 0;

        source.addEventListener("error", function (event) {
            switch (event.target.readyState) {
                case EventSource.CONNECTING:
                    document.getElementById('grocy-sse').textContent = 'Reconnecting...';
                    // console.log('Reconnecting...');
                    connectFailCounter++
                    if (connectFailCounter === 100) {
                        source.close();
                        document.getElementById('grocy-sse').textContent = 'Unavailable';
                        document.getElementById('scan-result').textContent = 'Unable to connect to Barcode Buddy';
                    }
                    break;
                case EventSource.CLOSED:
                    console.log('Connection failed (CLOSED)');
                    break;
            }
        }, false);

        async function resetScan(scanId) {
            await sleep(3000);
            if (currentScanId == scanId) {
                document.getElementById('content').style.backgroundColor = '#eee';
                document.getElementById('scan-result').textContent = 'waiting for barcode...';
                document.getElementById('event').textContent = '';
            }
        }

        function sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        function resultScan(color, message, text, sound) {
            document.getElementById('content').style.backgroundColor = color;
            document.getElementById('event').textContent = message;
            document.getElementById('scan-result').textContent = text;
            document.getElementById(sound).play();
            document.getElementById('log-entries').innerText = '\r\n' + text + document.getElementById('log-entries').innerText;
            currentScanId++;
            resetScan(currentScanId);
        }

        source.onopen = function () {
            document.getElementById('grocy-sse').textContent = 'Connected';
            if (isFirstStart) {
                isFirstStart = false;
                document.getElementById('scan-result').textContent = 'waiting for barcode...';
                var http = new XMLHttpRequest();
                http.open("GET", "incl/sse/sse_data.php?getState");
                http.send();
            }
        };

        source.onmessage = function (event) {
            var resultJson = JSON.parse(event.data);
            var resultCode = resultJson.data.substring(0, 1);
            var resultText = resultJson.data.substring(1);
            switch (resultCode) {
                case '0':
                    resultScan("#33a532", "", he.decode(resultText), "beep_success");
                    break;
                case '1':
                    resultScan("#a2ff9b", "Barcode Looked Up", he.decode(resultText), "beep_success");
                    break;
                case '2':
                    resultScan("#eaff8a", "Unknown Barcode", resultText, "beep_nosuccess");
                    break;
                case '4':
                    document.getElementById('mode').textContent = resultText;
                    break;
                case 'E':
                    document.getElementById('content').style.backgroundColor = '#CC0605';
                    document.getElementById('grocy-sse').textContent = 'disconnected';
                    document.getElementById('scan-result').style.display = 'none'
                    document.getElementById('previous-events').style.display = 'none'
                    document.getElementById('event').setAttribute('style', 'white-space: pre;');
                    document.getElementById('event').textContent = "\r\n\r\n" + resultText;
                    break;
            }
        };
    } else {
        document.getElementById('content').style.backgroundColor = '#f9868b';
        document.getElementById('grocy-sse').textContent = 'Disconnected';
        document.getElementById('event').textContent = 'Sorry, your browser does not support server-sent events';
    }
</script>

</body>
</html>
