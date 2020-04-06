<?php
/**
 * Barcode Buddy for Grocy
 *
 * PHP version 7
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 * 
 * Management for API keys
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.4
 */


require_once __DIR__ . "/../incl/configProcessing.inc.php";
require_once __DIR__ . "/../incl/db.inc.php";
require_once __DIR__ . "/../incl/webui.inc.php";
require_once __DIR__ . "/../incl/processing.inc.php";

$mobileKey = null;

if (isset($_POST["generate"])) {
	$db->generateApiKey();
    hideGetPostParameters();
}

if (isset($_POST["button_delete"])) {
    checkIfNumeric($_POST["button_delete"]);
    $db->deleteApiKey($_POST["button_delete"]);
    hideGetPostParameters();
}
if (isset($_POST["button_back"])) {
    hideGetPostParameters();
}

if (isset($_GET["mobileapp"])) {
    $mobileKey = $db->generateApiKey();
}
if (isset($_GET["deleteall"])) {
    $mobileKey = $db->deleteAllApiKeys();
    hideGetPostParameters();
}

$webUi = new WebUiGenerator(MENU_GENERIC);
$webUi->addHeader('<link rel="stylesheet" href="../incl/css/styleQr.css">');
$webUi->addHtml('<script type="text/javascript" src="/../incl/js/qrcode.js"></script>');
if ($mobileKey == null) 
    $webUi->addCard("API Keys", getApiTable(), createMenuLinks());
else
    $webUi->addCard("Add mobile app", getMobileAppPage());
$webUi->addFooter();
$webUi->printHtml();


function createMenuLinks() {
    $links = array();
    $linkAddApp = (new MenuItemLink())
                    ->setId("btn_apilinks")
                    ->setText("Add mobile app")
                    ->setLink('window.location.href=\''.$_SERVER['PHP_SELF'].'?mobileapp\'');
    $linkDelete = (new MenuItemLink())
                    ->setText("Revoke all")
                    ->setLink('window.location.href=\''.$_SERVER['PHP_SELF'].'?deleteall\'');
    $links[0] = $linkAddApp;
    $links[1] = $linkDelete;
    return $links;
}

function getMobileAppPage() {
    global $mobileKey;
    $apiUrl = getApiUrl("menu/");
    $infoArray = array ("issetup"=> true, "url" => $apiUrl, "key" => $mobileKey);
    $html = new UiEditor();
    $html->addHtml("Please scan the following QR code with your Barcode Buddy app. You can download the app here: XXXXXXXX");
    $html->addLineBreak(3);
    $html->addHtml('<div id="placeHolder"></div>');
    $html->addScript("var qr = qrcode(0, 'M');
    qr.addData('".json_encode($infoArray)."');
    qr.make();
    document.getElementById('placeHolder').innerHTML = qr.createImgTag(8,5);");
    $html->addLineBreak(3);
    $html->buildButton("button_back", "Go back")
                            ->setSubmit()
                            ->setIsAccent()
                            ->setRaised()
                            ->generate();
    return $html->getHtml();
}


function getApiTable() {
	global $db;
    $apikeys = $db->getStoredApiKeys();
    $html = new UiEditor();
    $html->addHtml("Management of API keys. For more information about the Barcode Buddy API, click <a href='/../api/'>here</a>");
    $html->addLineBreak(4);
    $table = new TableGenerator(array(
        "API Key",
        "Last Used",
        "Show QR Code",
        "Action"
    ));
    
    foreach ($apikeys as $apikey) {
        $table->startRow();
        $table->addCell($apikey['key']);
        $table->addCell($apikey['lastused']);
        $table->addCell($html->buildButton("button_qr", "QR Code")
                            ->setValue($apikey['id'])
                            ->setOnClick("showQrCode('".$apikey['key']."')")
                            ->generate(true));
        $table->addCell($html->buildButton("button_delete", "Revoke")
                            ->setSubmit()
                            ->setValue($apikey['id'])
                            ->generate(true));
        $table->endRow();
    }
    $html->addTableClass($table);
    $html->addLineBreak(2);
    $html->buildButton("generate","Add")
    						->setSubmit()
    						->setIsAccent()
    						->setRaised()
    						->generate();
    $html->addHtml('
        <div id="qrcode-modal" class="modal">
          <span class="close">&times;</span>
          <div class="modal-content" id="placeHolder"></div>
        </div>');
    $html->addScript("document.addEventListener('keyup', function(e) {
            if (e.keyCode == 27) {
                document.getElementById(\"qrcode-modal\").style.display = \"none\";
            }
        });");
    return $html->getHtml();
}



?>