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
 * Processing of all hard coded config
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.5
 */
           

const BB_VERSION = "1411";
const BB_VERSION_READABLE = "1.4.1.1";

const CONFIG_PATH = __DIR__ . '/../data/config.php';
const AUTHDB_PATH = __DIR__ . '/../data/users.db';


loadConfigPhp();
checkForMissingConstants();
global $CONFIG;
$CONFIG = new GlobalConfig();
$CONFIG->configureDebugOutput();
//For debugging:
//$CONFIG->echoConfig();

function loadConfigPhp() {
    if (!file_exists(CONFIG_PATH))
        createConfigPhp();
    require_once CONFIG_PATH;
}

function createConfigPhp() {
    require_once __DIR__ . "/processing.inc.php";
    if (!is_writable(dirname(CONFIG_PATH))) {
        showErrorNotWritable("FS Error DATA_PATH_NOT_WRITABLE");
    } else {
        $couldMove = copy(__DIR__ . '/../config-dist.php', CONFIG_PATH);
        if (!$couldMove) {
            showErrorNotWritable("FS Error COULD_NOT_MOVE");
        }
    }
}

function checkForMissingConstants() {
    $defaultValues = array(
                        "PORT_WEBSOCKET_SERVER"        => 47631,
                        "DATABASE_PATH"                => __DIR__ . '/../data/barcodebuddy.db',
                        "CONFIG_PATH"                  => CONFIG_PATH,
                        "AUTHDB_PATH"                  => AUTHDB_PATH,
                        "CURL_TIMEOUT_S"               => 20,
                        "CURL_ALLOW_INSECURE_SSL_CA"   => false,
                        "CURL_ALLOW_INSECURE_SSL_HOST" => false,
                        "IS_DOCKER"                    => false,
                        "REQUIRE_API_KEY"              => true,
                        "IS_DEBUG"                     => false,
                        "DISABLE_AUTHENTICATION"       => false,
                        "OVERRIDDEN_USER_CONFIG"       => array()
                        );
    foreach ($defaultValues as $key => $value) {
        if (!defined($key))
            define($key, $value);
    }
}


class GlobalConfig {
    public $PORT_WEBSOCKET_SERVER        = PORT_WEBSOCKET_SERVER;
    public $DATABASE_PATH                = DATABASE_PATH;
    public $CONFIG_PATH                  = CONFIG_PATH;
    public $AUTHDB_PATH                  = AUTHDB_PATH;
    public $CURL_TIMEOUT_S               = CURL_TIMEOUT_S;
    public $CURL_ALLOW_INSECURE_SSL_CA   = CURL_ALLOW_INSECURE_SSL_CA;
    public $CURL_ALLOW_INSECURE_SSL_HOST = CURL_ALLOW_INSECURE_SSL_HOST;
    public $IS_DOCKER                    = IS_DOCKER;
    public $REQUIRE_API_KEY              = REQUIRE_API_KEY;
    public $IS_DEBUG                     = IS_DEBUG;
    public $OVERRIDDEN_USER_CONFIG       = OVERRIDDEN_USER_CONFIG;
    public $DISABLE_AUTHENTICATION       = DISABLE_AUTHENTICATION;

   function __construct() {
        $this->loadConfig();
    }

    //Gets all the public variables declared above and checks if there
    //is an environment variable for this function. If yes, the
    //environment variable replaces the values in config.php
    private function loadConfig() {
        $environmentVariables = getenv();
        $reflect              = new ReflectionClass($this);
        $props                = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($props as $prop) {
            
            $variableName = $prop->getName();
            $variable =& $this->{$variableName};
            
            foreach ($environmentVariables as $envName => $envValue) {
                if ($envName == 'BBUDDY_' . $variableName) {
                    $variable = self::convertCorrectType($envValue, $variable);
                }
            }
        }
    }
    
    static private function convertCorrectType($input, $originalVar) {
        if (!is_array($originalVar)) {
            $variableType = gettype($originalVar);
            $result       = self::convertPossibleBoolean($input);
            settype($result, $variableType);
            return $result;
        } else
            return self::convertToArray($input);
        
    }
    
    //PHP converts String "false" to true...
    static private function convertPossibleBoolean($input) {
        if ($input === "true")
            return true;
        if ($input === "false")
            return false;
        return $input;
    }
    
    static private function convertToArray($input) {
        $result          = array();
        $passedArguments = explode(";", $input);
        foreach ($passedArguments as $argument) {
            if (strpos($argument, "=") !== false) {
                $content             = explode("=", $argument);
                $result[$content[0]] = self::convertPossibleBoolean($content[1]);
            }
        }
        return $result;
    }

    
    function echoConfig() {
        $environmentVariables = getenv();
        $reflect              = new ReflectionClass($this);
        $props                = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($props as $prop) {
            
            $variableName = $prop->getName();
            $variable =& $this->{$variableName};
            
            echo $variableName . ": ";
            var_dump($variable);
            echo "\n";
        }
    }

    public function configureDebugOutput() {
        //Enable debug as well if file "debug" exists in this directory
        if ($this->IS_DEBUG || file_exists(__DIR__ . "/debug")) {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
        }
    }

    public function checkIfAuthenticated($redirect = true, $ismenu = false) {
        global $auth;
        require_once __DIR__ . '/authentication/authentication.inc.php';
        if ($this->DISABLE_AUTHENTICATION)
            return true;
        else {
            $isLoggedIn = $auth->isLoggedIn();
            if (!$isLoggedIn && $redirect) {
                $location = "login.php";
                if ($ismenu)
                $location = "../login.php";
                header("Location: $location");
                die();
            } else
                return $isLoggedIn;
        }
    }
    
}



?>
