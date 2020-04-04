<?php 

const BB_VERSION          = "1411";
const BB_VERSION_READABLE = "1.4.1.1";


class GlobalConfig {
    public $PORT_WEBSOCKET_SERVER        = PORT_WEBSOCKET_SERVER;
    public $DATABASE_PATH                = DATABASE_PATH;
    public $CURL_TIMEOUT_S               = CURL_TIMEOUT_S;
    public $CURL_ALLOW_INSECURE_SSL_CA   = CURL_ALLOW_INSECURE_SSL_CA;
    public $CURL_ALLOW_INSECURE_SSL_HOST = CURL_ALLOW_INSECURE_SSL_HOST;
    public $IS_DOCKER                    = IS_DOCKER;
    public $REQUIRE_API_KEY              = REQUIRE_API_KEY;
    public $IS_DEBUG                     = IS_DEBUG;
    public $OVERRIDDEN_CONFIG            = OVERRIDDEN_CONFIG;
    
    //Gets all the public variables declared above and checks if there
    //is an environment variable for this function. If yes, the
    //environment variable replaces the values in config.php
    function __construct() {
        $environmentVariables = getenv();
        $reflect              = new ReflectionClass($this);
        $props                = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($props as $prop) {
            
            $variableName = $prop->getName();
            $variable     =& $this->{$variableName};
            $variableType = gettype($variable);
            
            foreach ($environmentVariables as $envName => $envValue) {
                if ($envName == 'BBUDDY_' . $variableName) {
                    if (!is_array($variable)) {
                        $variable = self::convertPossibleBoolean($envValue);
                        settype($variable, $variableType);
                    } else
                        $variable = explode(";", $envValue);
                }
            }
        }
    }
    
    //PHP converts String "false" to true...
    static private function convertPossibleBoolean($input) {
        if ($input === "true")
            return true;
        if ($input === "false")
            return false;
        return $input;
    }
    
}

$CONFIG = new GlobalConfig();

//Enable debug as well if file "debug" exists in this directory
if ($CONFIG->IS_DEBUG || file_exists(__DIR__ . "/debug")) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}



?>