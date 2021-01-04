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
 * Setup file
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.2
 */

if (version_compare(PHP_VERSION, '7.2.0') < 0) {
    echo 'PHP 7.2+ required, you are running ' . PHP_VERSION;
    die();
}


require_once __DIR__ . "/incl/configProcessing.inc.php";
require_once __DIR__ . "/incl/db.inc.php";
require_once __DIR__ . "/incl/webui.inc.php";
require_once __DIR__ . "/incl/api.inc.php";
require_once __DIR__ . "/incl/processing.inc.php";
require_once __DIR__ . "/incl/internalChecking.inc.php";
require_once __DIR__ . "/incl/config.inc.php";


$CONFIG->checkIfAuthenticated(true);

$result = true;


//If BB is already setup and for some reason an internal check failed
//(eg. missing php module), the use is redirected to setup.php
//If the problem is fixed and he clicks "Refresh", redirect him to index.php
//instead of showing the setup again
if (isset($_POST["was_internal_check"])) {
    if (BBConfig::getInstance()["GROCY_API_URL"] != null && BBConfig::getInstance()["GROCY_API_KEY"] != null) {
        header("Location: ./index.php");
        die();
    }
}

//If settings were submitted, save and check them
if (isset($_POST["GROCY_API_URL"])) {
    $apiWithTrailingSlash = rtrim($_POST["GROCY_API_URL"], '/') . '/';
    $result               = API::checkApiConnection($apiWithTrailingSlash, $_POST["GROCY_API_KEY"]);
    if ($result === true) {
        DatabaseConnection::getInstance()->updateConfig("GROCY_API_URL", sanitizeString($apiWithTrailingSlash));
        DatabaseConnection::getInstance()->updateConfig("GROCY_API_KEY", sanitizeString($_POST["GROCY_API_KEY"]));
        header("Location: ./index.php");
        die();
    }

}


$webUi = new WebUiGenerator(MENU_SETUP);
$webUi->addHeader();
if (checkExtensionsInstalled()["result"] == RESULT_REQ_MISSING) {
    $webUi->addCard("Setup", getHtmlSetupExtMissing());
} else {
    $webUi->addCard("Setup", getHtmlSetupTable($result));
}
$webUi->addFooter();
$webUi->printHtml();


function getHtmlSetupTable($result): string {
    $config = BBConfig::getInstance();
    $html   = new UiEditor();
    $html->addHtml('Welcome to Barcode Buddy! Please enter your Grocy API details below. For more information, please visit the <a target="_blank" href="https://barcodebuddy-documentation.readthedocs.io/en/latest/">documentation</a>.');
    $html->addLineBreak(3);
    $editValue = "";
    if (isset($_POST["GROCY_API_URL"]))
        $editValue = $_POST["GROCY_API_URL"];
    else if ($config["GROCY_API_URL"] != null)
        $editValue = $config["GROCY_API_URL"];
    $html->buildEditField('GROCY_API_URL', 'Grocy API URL', $editValue)
        ->pattern('https://.*/api/|http://.*/api/|https://.*/api|http://.*/api')
        ->setPlaceholder('e.g. https://your.grocy.com/api/')
        ->generate();
    $html->addLineBreak();
    $editValue = "";
    if (isset($_POST["GROCY_API_KEY"]))
        $editValue = $_POST["GROCY_API_KEY"];
    $html->buildEditField('GROCY_API_KEY', 'Grocy API Key', $editValue)
        ->pattern('[A-Za-z0-9]{50}')
        ->generate();
    if ($result !== true) {
        $html->addHtml('<br><span style="color: red; ">Unable to connect to API: ' . $result . '</span>');
    }
    $html->addLineBreak(2);
    $html->buildButton("button_save", "Save")
        ->setSubmit()
        ->setRaised()
        ->setIsAccent()
        ->generate();
    return $html->getHtml();
}


function getHtmlSetupExtMissing(): string {
    $html = new UiEditor();
    $html->addHtml('Welcome to Barcode Buddy! Please make sure the following extensions are installed and enabled:');
    $html->addLineBreak(2);
    $extensions = checkExtensionsInstalled();
    foreach ($extensions["req"] as $ext => $installed) {
        $html->addHtml(formatExtentioncolor($ext, $installed, true));
    }
    foreach ($extensions["opt"] as $ext => $installed) {
        $html->addHtml(formatExtentioncolor($ext, $installed, false));
    }
    $html->addLineBreak(2);
    $html->addHiddenField("was_internal_check", "1");
    $html->buildButton("button_refresh", "Refresh")
        ->setSubmit()
        ->setRaised()
        ->setIsAccent()
        ->generate();
    return $html->getHtml();
}

function formatExtentioncolor($name, $installed, $req): string {
    if ($installed) {
        return 'Installed: <span style="color:green">' . $name . '</span><br>';
    } else {
        if ($req) {
            return '<b>Not installed: <span style="color:red">' . $name . ' (required)</span></b><br>';
        } else {
            return 'Not installed: <span style="color:#d0a42c">' . $name . ' (optional)</span><br>';
        }
    }
}

