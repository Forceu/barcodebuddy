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
require_once __DIR__ . "/../incl/config.inc.php";

//removes Get parameters
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

    /**
     * Checks if authorized
     * @return bool True if authorized, or dies if not
     * @throws DbConnectionDuringEstablishException
     */
    function checkIfAuthorized(): bool {
        global $CONFIG;

        if ($CONFIG->checkIfAuthenticated(false))
            return true;

        $apiKey = "";
        if (isset($_SERVER["HTTP_BBUDDY_API_KEY"]))
            $apiKey = $_SERVER["HTTP_BBUDDY_API_KEY"];
        if (isset($_GET["apikey"]))
            $apiKey = $_GET["apikey"];

        if ($apiKey == "")
            self::sendUnauthorizedAndDie();

        if (DatabaseConnection::getInstance()->isValidApiKey($apiKey))
            return true;
        else
            self::sendUnauthorizedAndDie();
        return false;
    }

    static function sendUnauthorizedAndDie(): void {
        self::sendResult(self::createResultArray(null, "Unauthorized", 401), 401);
        die();
    }

    function execute(string $url): void {
        global $CONFIG;

        //Turn off all error reporting, as it could cause problems with parsing json clientside
        if (!$CONFIG->IS_DEBUG)
            error_reporting(0);

        if (!isset($this->routes[$url])) {
            self::sendResult(self::createResultArray(null, "API call not found", 404), 404);
        } else {
            $this->routes[$url]->execute();
        }
    }


    function __construct() {
        $this->initRoutes();
    }


    /**
     * @param array|null $data
     * @param string $result
     * @param int $http_int
     * @return array (array|mixed)[]
     */
    static function createResultArray(array $data = null, string $result = "OK", int $http_int = 200): array {
        return array(
            "data" => $data,
            "result" => array(
                "result" => $result,
                "http_code" => $http_int
            )
        );
    }

    function addRoute(ApiRoute $route): void {
        $this->routes[$route->path] = $route;
    }

    private function initRoutes(): void {

        $this->addRoute(new ApiRoute("/action/scan", function () {
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
                $bestBefore = null;
                $price      = null;
                if (isset($_POST["bestBeforeInDays"]) && $_POST["bestBeforeInDays"] != null) {
                    if (is_numeric($_POST["bestBeforeInDays"]))
                        $bestBefore = $_POST["bestBeforeInDays"];
                    else
                        return self::createResultArray(null, "Invalid parameter bestBeforeInDays: needs to be type int", 400);
                }
                if (isset($_POST["price"]) && $_POST["price"] != null) {
                    if (is_numeric($_POST["price"]))
                        $price = $_POST["price"];
                    else
                        return self::createResultArray(null, "Invalid parameter price: needs to be type float", 400);
                }
                $result = processNewBarcode(sanitizeString($barcode), $bestBefore, $price);
                return self::createResultArray(array("result" => sanitizeString($result)));
            }
        }));

        $this->addRoute(new ApiRoute("/state/getmode", function () {
            return self::createResultArray(array(
                "mode" => DatabaseConnection::getInstance()->getTransactionState()
            ));
        }));

        $this->addRoute(new ApiRoute("/state/setmode", function () {
            $state = null;
            if (isset($_GET["state"]))
                $state = $_GET["state"];
            else if (isset($_POST["state"]))
                $state = $_POST["state"];            

            //Also check if value is a valid range (STATE_CONSUME the lowest and STATE_CONSUME_ALL the highest value)
            if (!is_numeric($state) || $state < STATE_CONSUME || $state > STATE_CONSUME_ALL)
                return self::createResultArray(null, "Invalid state provided", 400);
            else {
                DatabaseConnection::getInstance()->setTransactionState(intval($state));
                return self::createResultArray();
            }
        }));

        $this->addRoute(new ApiRoute("/system/barcodes", function () {
            $config = BBConfig::getInstance();
            return self::createResultArray(array(
                "BARCODE_C" => $config["BARCODE_C"],
                "BARCODE_CS" => $config["BARCODE_CS"],
                "BARCODE_P" => $config["BARCODE_P"],
                "BARCODE_O" => $config["BARCODE_O"],
                "BARCODE_GS" => $config["BARCODE_GS"],
                "BARCODE_Q" => $config["BARCODE_Q"],
                "BARCODE_AS" => $config["BARCODE_AS"],
                "BARCODE_CA" => $config["BARCODE_CA"]
            ));
        }));

        $this->addRoute(new ApiRoute("/system/info", function () {
            return self::createResultArray(array(
                "version" => BB_VERSION_READABLE,
                "version_int" => BB_VERSION
            ));
        }));
    }


    /**
     * @return never
     */
    static function sendResult(array $data, int $result): void {
        header('Content-Type: application/json');
        http_response_code($result);
        echo trim(json_encode($data, JSON_HEX_QUOT));
        die();
    }

}


class ApiRoute {

    public $path;
    private $function;

    /**
     * @param string $path API path
     */
    function __construct(string $path, $function) {
        $this->path     = '/api' . $path;
        $this->function = $function;
    }

    function execute(): void {
        $result = $this->function->__invoke();
        BBuddyApi::sendResult($result, $result["result"]["http_code"]);
    }
}
