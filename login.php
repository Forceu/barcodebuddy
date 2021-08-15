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
 * Login file
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.5
 */


require_once __DIR__ . "/incl/configProcessing.inc.php";
require_once __DIR__ . "/incl/webui.inc.php";
require_once __DIR__ . "/incl/processing.inc.php";
require_once __DIR__ . '/incl/authentication/authentication.inc.php';

$result = null;

if (isset($_POST["button_create"])) {
    createUser();
}


if (isset($_POST["button_login"])) {
    login();
}


if ($CONFIG->checkIfAuthenticated(false)) {
    header("Location: ./index.php");
    die();
}


$webUi = new WebUiGenerator(MENU_LOGIN);
$webUi->addHeader();
if (isUserSetUp())
    $webUi->addCard("Login", getHtmlLogin($result));
else
    $webUi->addCard("Create User", getHtmlCreateUser($result));
$webUi->addFooter();
$webUi->printHtml();


function login(): void {
    global $result;
    global $auth;

    try {
        $auth->loginWithUsername($_POST['username'], $_POST['password'], (int)(60 * 60 * 24 * 365.25));
        header("Location: ./index.php");
        die();
    } catch (\Delight\Auth\UnknownUsernameException $e) {
        $result = 'Wrong username or password';
    } catch (\Delight\Auth\InvalidPasswordException $e) {
        $result = 'Wrong username or password';
    } catch (\Delight\Auth\TooManyRequestsException $e) {
        $result = 'Too many requests';
    }
}


function createUser(): void {
    global $result;
    global $auth;
    if (isUserSetUp())
        die("Unauthorized");
    if (strlen($_POST["username"]) < 2) {
        $result = "The username needs to be at least 2 characters long";
    }
    if (strlen($_POST["password"]) < 6) {
        $result = "The password needs to be at least 6 characters long";
    }
    if ($_POST["password"] != $_POST["password_r"]) {
        $result = "Passwords don't match";
    }
    //No error has occured
    if ($result == null) {
        changeUserName(sanitizeString($_POST["username"]));
        $auth->admin()->changePasswordForUserById(1, $_POST['password']);
        $auth->admin()->addRoleForUserById(1, \Delight\Auth\Role::ADMIN);
        $auth->admin()->logInAsUserById(1);
        header("Location: ./index.php");
        die();
    }
}

function getHtmlCreateUser(?string $result): string {
    $html = new UiEditor();
    $html->addHtml("Please enter a username and password:");
    $html->addLineBreak(2);
    $editValue = "";
    if (isset($_POST["username"]))
        $editValue = $_POST["username"];
    $html->buildEditField('username', 'Username', $editValue)
        ->minlength(2)
        ->generate();
    $html->addLineBreak();
    $html->buildEditField('password', 'Password')
        ->minlength(6)
        ->type("password")
        ->generate();
    $html->addLineBreak();
    $html->buildEditField('password_r', 'Password (repeat)')
        ->minlength(6)
        ->type("password")
        ->generate();
    if ($result != null) {
        $html->addLineBreak(2);
        $html->addHtml('<font color="red">' . $result . '</font>');
    }
    $html->addLineBreak(2);
    $html->buildButton("button_create", "Create")
        ->setSubmit()
        ->setRaised()
        ->setIsAccent()
        ->generate();
    $html->addLineBreak(3);
    $html->addHtml("<small><i>Note: If you do not want to use authentication, you can disable it by editing /data/config.php</i></small>");
    return $html->getHtml();
}


function getHtmlLogin(?string $result): string {
    global $CONFIG;
    $html      = new UiEditor();
    $editValue = "";
    if (isset($_POST["username"]))
        $editValue = $_POST["username"];
    $html->buildEditField('username', 'Username', $editValue)
        ->generate();
    $html->addLineBreak();
    $html->buildEditField('password', 'Password')
        ->type("password")
        ->generate();
    if ($result != null) {
        $html->addLineBreak(2);
        $html->addHtml('<span style="color: red; ">' . $result . '</span>');
    }
    $html->addLineBreak(2);
    $html->buildButton("button_login", "Login")
        ->setSubmit()
        ->setRaised()
        ->setIsAccent()
        ->generate();
    $pathUsers = realpath($CONFIG->AUTHDB_PATH);
    $html->buildButton("button_forgot", "Forgot Password")
        ->setOnClick("alert('If you forgot your password, please delete the file $pathUsers')")
        ->generate();
    return $html->getHtml();
}
