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

$db = DatabaseConnection::getInstance();

$CONFIG->checkIfAuthenticated(true, true);

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

if (isset($_POST["generate_app"])) {
    $mobileKey = $db->generateApiKey();
}
if (isset($_GET["deleteall"])) {
    $db->deleteAllApiKeys();
    hideGetPostParameters();
}

$webUi = new WebUiGenerator(MENU_GENERIC);
$webUi->addHeader('<link rel="stylesheet" href="../incl/css/styleQr.css">');
$webUi->addHtml('<script src="../incl/js/qrcode.js"></script>');
$link = (new MenuItemLink())
    ->setId("btn_apilinks")
    ->setText("Revoke all")
    ->setLink('window.location.href=\'' . $CONFIG->getPhpSelfWithBaseUrl() . '?deleteall\'');
if ($mobileKey == null)
    $webUi->addCard("API Keys", getApiTable(), $link);
else
    $webUi->addCard("Add mobile app", getMobileAppPage());
$webUi->addFooter();
$webUi->printHtml();


function getMobileAppPage(): string {
    global $mobileKey;
    $apiUrl    = getApiUrl("menu/");
    $infoArray = array("issetup" => true, "url" => $apiUrl, "key" => $mobileKey);
    $html      = new UiEditor();
    $html->addHtml("Please scan the following QR code with your Barcode Buddy app. You can download the Android app <a target='_blank' href='https://play.google.com/store/apps/details?id=de.bulling.barcodebuddyscanner'>here</a>");
    $html->addLineBreak(3);
    $html->addHtml('<div class="flex-settings">');
    $html->addDiv('<div id="placeHolder"></div>', null, "flex-settings-child");
    $html->addHtml('<div class="flex-settings-child">');
    $html->buildEditField('qr_url', 'URL', $apiUrl)
        ->onKeyUp('updateQrCode()')
        ->onfocusout('updateQrCode()')
        ->generate();
    $html->addLineBreak();
    $html->buildEditField('qr_key', 'API key', $mobileKey)->disabled()->generate();
    $html->addHtml('</div></div>');
    $html->addLineBreak(3);
    $html->buildButton("button_back", "Go back")
        ->setSubmit()
        ->setIsAccent()
        ->setRaised()
        ->generate();
    $html->addScript('var qrData =[];
                      qrData["issetup"] = true;
                      qrData["url"] = "' . $apiUrl . '";
                      qrData["key"] = "' . $mobileKey . '";
                      generateAppQrCode(qrData);');
    return $html->getHtml();
}


function getApiTable(): string {
    global $CONFIG;

    $apikeys = DatabaseConnection::getInstance()->getStoredApiKeys();
    $html    = new UiEditor();
    $html->addHtml("Management of API keys. For more information about the Barcode Buddy API, click <a  target='_blank' href='../api/'>here</a>.");
    if (!$CONFIG->REQUIRE_API_KEY) {
        $html->addLineBreak(2);
        $html->addHtml("<i>API key authentication is currently <b>disabled</b>.</i>");
        $html->addLineBreak(2);
    } else
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
            ->setOnClick("showQrCode('" . $apikey['key'] . "')")
            ->generate(true));
        $table->addCell($html->buildButton("button_delete", "Revoke")
            ->setSubmit()
            ->setValue($apikey['id'])
            ->generate(true));
        $table->endRow();
    }
    $html->addTableClass($table);
    $html->addLineBreak(2);
    $html->buildButton("generate_app", "Add mobile app")
        ->setSubmit()
        ->setIsAccent()
        ->setRaised()
        ->generate();
    $html->buildButton("generate", "New API key")
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