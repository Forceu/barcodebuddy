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
 * @since      File available since Release 1.8
 */


require_once __DIR__ . "/../incl/configProcessing.inc.php";
require_once __DIR__ . "/../incl/processing.inc.php";
require_once __DIR__ . "/../incl/webui.inc.php";


$CONFIG->checkIfAuthenticated(true, true);


if (isset($_POST["federation_agree"])) {
    BarcodeFederation::enableFederation();
    header("Location: " . $CONFIG->getPhpSelfWithBaseUrl());
    die();
}
if (isset($_POST["federation_disable"])) {
    BarcodeFederation::disableFederation();
    header("Location: " . $CONFIG->getPhpSelfWithBaseUrl());
    die();
}

$webUi = new WebUiGenerator(MENU_GENERIC);
$webUi->addHeader();

$loadingSpinner =  (new UiEditor())->addLoadingSpinner("loading",true);
    $webUi->addCard('Barcode Buddy Federation <span id="statusTop">'.$loadingSpinner.'</span>', getHtmlFederation());
    $webUi->addCard('Info ', getHtmlFederationInfo());
$webUi->addFooter();
$webUi->printHtml();


function getHtmlFederation(): string {
    $config = BBConfig::getInstance();
    $html   = new UiEditor();
    $html->addHtml("Barcode Buddy Federation enables you to lookup barcodes that other users have already associated with a Grocy product before.
                          <br>It requires no signup, but is limited to 200 requests per day.");
    if (!$config["BBUDDY_SERVER_ENABLED"]) {
        $html->addLineBreak(2);
        $html->addHtml("<span style='font-size: smaller'><i>By using this method, you agree that all your Grocy product names that are associated with barcodes, the barcodes itself, your IP address and an
                          unique ID will be sent and stored at a remote Barcode Buddy server located in Germany. No other data is sent or stored and no data will be used
                          for commercial purposes. The sourcecode for the server application is available <a href='https://github.com/Forceu/barcodebuddy-federation/'>on Github</a> and can be selfhosted as well.</i></span>");
    }

    $html->addLineBreak(3);
    if ($config["BBUDDY_SERVER_ENABLED"]) {
        $html->buildButton("federation_disable", "Disable Federation")
            ->setRaised()
            ->setSubmit()
            ->generate();
    } else {
        $html->buildButton("federation_agree", "Use Federation")
            ->setIsAccent()
            ->setRaised()
            ->setSubmit()
            ->generate();
    }
    $html->addHtml("<script>
            ".BarcodeFederation::getJsPing("isOnline")."
            ".BarcodeFederation::getJsAmount("gotAmount")."
            
            function isOnline(online) {
                let status = document.getElementById('statusTop');
                if (online) {
                    status.innerHTML='<span style=\"color:forestgreen\">available</span>';
                } else {
                    status.innerHTML='<span style=\"color:darkred\">offline</span>';
                }
            }
            
            
            function gotAmount(result, responsetime) {
                let status = document.getElementById('infoStatus');
                let responseTime = document.getElementById('infoResponse');
                let amount = document.getElementById('infoAmount');
                if (result === false) {
                    status.style=\"color:darkred\";
                    status.innerHTML=\"offline\";
                } else {
                    status.style=\"color:forestgreen\";
                    status.innerHTML=\"online\";
                    responseTime.innerHTML=responsetime+\" ms\";
                    amount.innerHTML=result+\" (updated every 6 hours)\";
                }
            }
                
                
            
            window.addEventListener(\"load\", function(){
                isFederationOnline();
                getFedereationAmount();
            });
                </script>");
    return $html->getHtml();
}


function getHtmlFederationInfo(): string {
    $html         = new UiEditor();
    $html->addHtml("<b>Status:</b> <span id='infoStatus'>Loading...</span><br>
                         <b>Response Time: </b><span id='infoResponse'></span><br>
                         <b>Barcodes stored:</b> <span id='infoAmount'></span>");
    return $html->getHtml();
}