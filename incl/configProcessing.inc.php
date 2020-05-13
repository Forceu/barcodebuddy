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
           

const BB_VERSION = "1503";
const BB_VERSION_READABLE = "1.5.0.3-dev";

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
    $env        = getenv();
    $configPath = CONFIG_PATH;
    if (isset($env["BBUDDY_CONFIG_PATH"])) {
        $configPath = $env["BBUDDY_CONFIG_PATH"];
    } elseif (isset($_SERVER["BBUDDY_CONFIG_PATH"])) {
        $configPath = $_SERVER["BBUDDY_CONFIG_PATH"];
    }
    if (!file_exists($configPath))
        createConfigPhp($configPath);
    require_once $configPath;
}

function createConfigPhp($configPath) {
    require_once __DIR__ . "/processing.inc.php";
    if (!is_writable(dirname($configPath))) {
        showErrorNotWritable("FS Error DATA_PATH_NOT_WRITABLE");
    } else {
        $couldMove = copy(__DIR__ . '/../config-dist.php', $configPath);
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
                        "HIDE_LINK_GROCY"              => false,
                        "HIDE_LINK_SCREEN"             => false,
                        "EXTERNAL_GROCY_URL"           => null,
                        "OVERRIDDEN_USER_CONFIG"       => array(),
                        "SCREEN_TOUCH_BUTTONS"         => false,
                        "AUTHENTICATION_BYPASS_NETS"   => array(),
                        "TRUSTED_PROXIES"              => array()
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
    public $HIDE_LINK_GROCY              = HIDE_LINK_GROCY;
    public $HIDE_LINK_SCREEN             = HIDE_LINK_SCREEN;
    public $EXTERNAL_GROCY_URL           = EXTERNAL_GROCY_URL;
    public $SCREEN_TOUCH_BUTTONS         = SCREEN_TOUCH_BUTTONS;
    public $AUTHENTICATION_BYPASS_NETS   = AUTHENTICATION_BYPASS_NETS;
    public $TRUSTED_PROXIES              = TRUSTED_PROXIES;

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

            $configString = 'BBUDDY_' . $variableName;
            if (isset($environmentVariables[$configString])) {
                $variable = self::convertCorrectType($environmentVariables[$configString], $variable);
            } elseif (isset($_SERVER[$configString])) {
                $variable = self::convertCorrectType($_SERVER[$configString], $variable);
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
        
        // Check if authentication is disabled globally
        if ($this->DISABLE_AUTHENTICATION)
            return true;
        
        // If authentication is at all enabled, ensure the user has completed first-time setup
        if (!isUserSetUp()) {
             if ($redirect) {
                $location = "login.php";
                if ($ismenu)
                $location = "../login.php";
                header("Location: $location");
                die();
            } else
                return false;
        }
        
        // Check if IP is available for subnet-based authentication
        $ip = $this->getIpAddress();
        if ($ip) {
            // Check if any trusted subnets match the client IP
            $trusted_subnet = array_filter($this->AUTHENTICATION_BYPASS_NETS, function($subnet) use ($ip) {return $this->ipInSubnet($ip, $subnet);});
            
            // if any subnet matches, bypass authentication
            if(sizeof($trusted_subnet) > 0)
                return true;
        }
        
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
    
    /**
     * Returns client IP address.
     *
     * @return false|string IP address.
     */
    private function getIpAddress() {
        // Check if any remote addresses are available
        if (!isset($_SERVER['REMOTE_ADDR'])) {
            return false;
        }
        
        // Check if using a trusted proxy
        $proxy_trusted = array_filter($this->TRUSTED_PROXIES, function($subnet) {
            return $this->ipInSubnet($_SERVER['REMOTE_ADDR'], $subnet);});
        if(sizeof($proxy_trusted) == 0) {
            // Remote address is not a proxy, use this as the client's IP
            return $_SERVER['REMOTE_ADDR'];
        }
        
        // If so, check if the proxy has sent an X-Forwarded-For header
        if (!isset($_SERVER["HTTP_X_FORWARDED_FOR"]) || empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            return false;
        }
        
        // Get client IPs from the proxy
        $ips = explode(',', $_SERVER["HTTP_X_FORWARDED_FOR"]);
        // trim, so we can compare against trusted proxies properly
        $ips = array_map('trim', $ips);
        // remove trusted proxy IPs
        $ips = array_diff($ips, $this->trustedProxies);
        
        // Any left?
        if (empty($ips)) {
            return false;
        }
        
        // Since we've removed any known, trusted proxy servers, the right-most
        // address represents the first IP we do not know about -- i.e., we do
        // not know if it is a proxy server, or a client. As such, we treat it
        // as the originating IP.
        // @see http://en.wikipedia.org/wiki/X-Forwarded-For
        $ip = array_pop($ips);
        return $ip;
    }
    
    private function ipInSubnet($ip, $subnet) {
        $subnetComponents = explode("/", $subnet);
        $subnetAddress = $subnetComponents[0];
        
        if (sizeof($subnetComponents) == 2) {
            // Subnet is a full network
            $subnetMask = $subnetComponents[1];
            
            if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnetAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                // IP and Subnet are both IPv4
                // Convert IP and subnet to integers
                $ip = ip2long($ip);
                $subnetAddress = ip2long($subnetAddress);
                
                // Trim both addresses to match the mask
                $mask = -1 << (32 - $subnetMask);
                $ip &= $mask;
                $subnetAddress &= $mask;
                
                // Compare the remaining addresses
                return $ip == $subnetAddress;
                
            } else if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && filter_var($subnetAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                // IP and Subnet are both IPv6
                // Convert IP to raw bits
                $ip = inet_pton($ip);
                $binaryIp = $this->inet_to_bits($ip);
                
                // convert subnet to raw bits
                $subnetAddress = inet_pton($subnetAddress);
                $binarySubnet = $this->inet_to_bits($subnetAddress);
                
                // Trim both addresses to match the mask
                $ip_net_bits = substr($binaryIp, 0, $subnetMask);
                $net_bits = substr($binarySubnet, 0, $subnetMask);
                
                // Compare the remaining addresses
                return $ip_net_bits == $net_bits;
            } else {
                // IP and Subnet are different IP versions
                return false;
            }
        } else {
            // Subnet is single address, use direct comparison
            return $ip == $subnetAddress;
        }
    }
    
    // IPv6 address to list of bits.
    private function inet_to_bits($inet) {
        $unpacked = unpack('A16', $inet);
        $unpacked = str_split($unpacked[1]);
        $binaryip = '';
        foreach ($unpacked as $char) {
            $binaryip .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        return $binaryip;
    }
}
