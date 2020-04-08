<?php

/**
 * Barcode Buddy for Grocy
 *
 * PHP version 7
 *
 * API controller
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.4
 */



require_once __DIR__ . "/../incl/configProcessing.inc.php";
require_once __DIR__ . "/../incl/db.inc.php";
require_once __DIR__ . "/../incl/processing.inc.php";

//removes Get paramterss
$requestedUrl = strtok($_SERVER["REQUEST_URI"], '?');

//removes everything before "/api"
$requestedUrl = trim(substr($requestedUrl, strpos($requestedUrl, '/api')));

$api = new BBuddyApi();
if ($requestedUrl == "/api/") {
    readfile(__DIR__ . "/doc.html");
    die();
}
if ($CONFIG->REQUIRE_API_KEY)
	$api->checkIfAuthorized();
$api->execute($requestedUrl);



class BBuddyApi {
    
    private $routes = array();
    
    function checkIfAuthorized() {
        global $db;
        
        $apiKey = "";
        if (isset($_SERVER["HTTP_BBUDDY_API_KEY"]))
            $apiKey = $_SERVER["HTTP_BBUDDY_API_KEY"];
        if (isset($_GET["apikey"]))
            $apiKey = $_GET["apikey"];
        
        if ($apiKey == "")
            self::sendUnauthorizedAndDie();
        
        if ($db->isValidApiKey($apiKey))
            return true;
        else
            self::sendUnauthorizedAndDie();
    }
    
    static function sendUnauthorizedAndDie() {
        self::sendResult(self::createResultArray(null, "Unauthorized", 401), 401);
        die();
    }
    
    function execute($url) {
        if (!isset($this->routes[$url])) {
            self::sendResult(self::createResultArray(null, "API call not found", 404), 404);
        } else {
            $this->routes[$url]->execute();
        }
    }
    
    
    function __construct() {
        $this->initRoutes();
    }
    
    
    static function createResultArray($data = null, $result = "OK", $http_int = 200) {
        return array(
            "data" => $data,
            "result" => array(
                "result" => $result,
                "http_code" => $http_int
            )
        );
    }
    
    function addRoute($route) {
        $this->routes[$route->path] = $route;
    }
    
    private function initRoutes() {
        
        $this->addRoute(new ApiRoute("/action/scan", function() {
            $barcode = "";
            if (isset($_GET["text"]))
                $barcode = $_GET["text"];
            if (isset($_GET["add"]))
                $barcode = $_GET["add"];
            if (isset($_POST["barcode"]))
                $barcode = $_POST["barcode"];
            if ($barcode == "")
                return self::createResultArray(null, "No barcode supplied", 400);
            else {
                $result = processNewBarcode(sanitizeString($barcode));
                return self::createResultArray(array("result" => $result));
            }
        }));
        
        $this->addRoute(new ApiRoute("/state/getmode", function() {
            global $db;
            return self::createResultArray(array(
                "mode" => $db->getTransactionState()
            ));
        }));
        
        $this->addRoute(new ApiRoute("/state/setmode", function() {
            global $db;
            if (!isset($_POST["state"]) || !is_numeric($_POST["state"]))
                return self::createResultArray(null, "Invalid state provided", 400);
            else {
                $db->getTransactionState($_POST["state"]);
                return self::createResultArray();
            }
        }));
        
        $this->addRoute(new ApiRoute("/system/barcodes", function() {
            global $BBCONFIG;
            return self::createResultArray(array(
                "BARCODE_C" => $BBCONFIG["BARCODE_C"],
                "BARCODE_CS" => $BBCONFIG["BARCODE_CS"],
                "BARCODE_P" => $BBCONFIG["BARCODE_P"],
                "BARCODE_O" => $BBCONFIG["BARCODE_O"],
                "BARCODE_GS" => $BBCONFIG["BARCODE_GS"],
                "BARCODE_Q" => $BBCONFIG["BARCODE_Q"],
                "BARCODE_AS" => $BBCONFIG["BARCODE_AS"]
            ));
        }));
        
        $this->addRoute(new ApiRoute("/system/info", function() {
            return self::createResultArray(array(
                "version"    => BB_VERSION_READABLE,
                "version_int" => BB_VERSION
            ));
        }));
    }
    
    
    
    static function sendResult($data, $result) {
        header('Content-Type: application/json');
        http_response_code($result);
        echo json_encode($data);
        die();
    }
    
}


class ApiRoute {
    
    public $path;
    private $function;
    
    function __construct($path, $function) {
        $this->path     = '/api' . $path;
        $this->function = $function;
    }
    
    function execute() {
        $result = $this->function->__invoke();
        BBuddyApi::sendResult($result, $result["result"]["http_code"]);
    }
}

?>