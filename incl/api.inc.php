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
 * Helper file for Grocy API and barcode lookup
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */


require_once __DIR__ . "/configProcessing.inc.php";
require_once __DIR__ . "/db.inc.php";
require_once __DIR__ . "/config.inc.php";
require_once __DIR__ . "/redis.inc.php";
require_once __DIR__ . "/curl.inc.php";

const API_O_BARCODES       = 'objects/product_barcodes';
const API_O_PRODUCTS       = 'objects/products';
const API_STOCK_PRODUCTS   = 'stock/products';
const API_ALL_PRODUCTS     = 'stock';
const API_SHOPPINGLIST     = 'stock/shoppinglist/';
const API_CHORES           = 'objects/chores';
const API_STOCK            = 'stock/products';
const API_STOCK_BY_BARCODE = 'stock/products/by-barcode/';
const API_CHORE_EXECUTE    = 'chores/';
const API_SYTEM_INFO       = 'system/info';
const API_SYTEM_TIME       = 'system/time';

const MIN_GROCY_VERSION = "3.0.0"; //TODO replace with 3.0.1 once it is released
const USE_OLD_300_API   = true; //TODO DELETE once 3.0.1 is released

const LOGIN_URL     = "loginurl";
const LOGIN_API_KEY = "loginkey";

const DISPLAY_DEBUG = false;


class GrocyProduct {
    public $id;
    public $name;
    public $barcodes = null;
    public $unit = null;
    public $stockAmount = "0";
    public $isTare;
    public $tareWeight;
    public $quFactor;
    public $defaultBestBeforeDays;
    public $creationDate;

    public static function parseProductInfoStock($infoArray): GrocyProduct {
        checkIfNumeric($infoArray["product"]["id"]);

        $result                        = new GrocyProduct();
        $result->id                    = $infoArray["product"]["id"];
        $result->name                  = sanitizeString($infoArray["product"]["name"]);
        $result->isTare                = ($infoArray["product"]["enable_tare_weight_handling"] == "1");
        $result->tareWeight            = sanitizeString($infoArray["product"]["tare_weight"]);
        $result->quFactor              = sanitizeString($infoArray["product"]["qu_factor_purchase_to_stock"]);
        $result->defaultBestBeforeDays = $infoArray["product"]["default_best_before_days"];
        $result->creationDate          = $infoArray["product"]["row_created_timestamp"];
        $result->unit                  = sanitizeString($infoArray["quantity_unit_stock"]["name"]);
        $result->barcodes              = $infoArray["product_barcodes"];

        if (sanitizeString($infoArray["stock_amount"]) != null)
            $result->stockAmount = sanitizeString($infoArray["stock_amount"]);
        return $result;
    }


    public static function parseProductInfoObjects($infoArray): GrocyProduct {
        checkIfNumeric($infoArray["id"]);

        $result                        = new GrocyProduct();
        $result->id                    = $infoArray["id"];
        $result->name                  = sanitizeString($infoArray["name"]);
        $result->isTare                = ($infoArray["enable_tare_weight_handling"] == "1");
        $result->tareWeight            = sanitizeString($infoArray["tare_weight"]);
        $result->quFactor              = sanitizeString($infoArray["qu_factor_purchase_to_stock"]);
        $result->defaultBestBeforeDays = $infoArray["default_best_before_days"];
        $result->creationDate          = $infoArray["row_created_timestamp"];
        return $result;
    }
}

class API {

    /**
     * Getting info all Grocy products.
     *
     * @param bool $ignoreCache If true, cache will be ignored and afterwards updated.
     * @return GrocyProduct[]|null Array of products
     */
    public static function getAllProductsInfo($ignoreCache = false): ?array {
        $updateRedisCache = false;

        if (RedisConnection::isRedisAvailable()) {
            if (!$ignoreCache && RedisConnection::isCacheAvailable()) {
                $cachedResult = RedisConnection::getAllProductsInfo();
                if ($cachedResult != null)
                    return $cachedResult;
            }
            $updateRedisCache = true;
        }

        $result = null;  // Assure assignment in event curl throws exception.
        $curl   = new CurlGenerator(API_O_PRODUCTS);
        try {
            $result = $curl->execute(true);
        } catch (Exception $e) {
            self::processError($e, "Could not lookup Grocy product info");
        }
        if ($result != null) {
            $products = array();
            foreach ($result as $product) {
                array_push($products, GrocyProduct::parseProductInfoObjects($product));
            }
            if ($updateRedisCache) {
                RedisConnection::cacheAllProductsInfo($products);
            }
            return $products;
        }
        return null;
    }

    /**
     * Getting info about one Grocy product.
     *
     * @param string ProductId
     * @return GrocyProduct Product info or array of products
     */
    public static function getProductInfo($productId): ?GrocyProduct {
        $apiurl = API_STOCK_PRODUCTS . "/" . $productId;

        $result = null;  // Assure assignment in event curl throws exception.
        $curl   = new CurlGenerator($apiurl);
        try {
            $result = $curl->execute(true);
        } catch (Exception $e) {
            self::processError($e, "Could not lookup Grocy product info");
        }
        if ($result != null) {
            if (isset($result["product"]["id"])) {
                return GrocyProduct::parseProductInfoStock($result);
            } else {
                return null;
            }
        }
        return null;
    }

    /**
     * Gets the last created product
     * @param int $timeframeInMinutes Specify how many old the product can be max (in minutes) or pass 0 for unlimited age
     * @return GrocyProduct|null
     */
    public static function getLastCreatedProduct(int $timeframeInMinutes = 0): ?GrocyProduct {
        $products    = self::getAllProductsInfo();
        $lastProduct = null;
        if ($timeframeInMinutes < 1)
            $latestDate = "0";
        else
            $latestDate = self::getLocalTimeGrocy($timeframeInMinutes * -60);
        foreach ($products as $product) {
            if ($product->creationDate > $latestDate) {
                $lastProduct = $product;
                $latestDate  = $product->creationDate;
            }
        }
        return $lastProduct;
    }

    /**
     * Gets local time from Grocy (all db entries are in local time)
     * @param int $offset offset to time in seconds
     * @return string
     */
    public static function getLocalTimeGrocy(int $offset = 0): string {
        if (USE_OLD_300_API) { //TODO DELETE once 3.0.1 is released
            return "0";
        }
        $apiurl = API_SYTEM_TIME . "?offset=" . $offset;
        $curl   = new CurlGenerator($apiurl);
        try {
            $result = $curl->execute(true);
        } catch (Exception $e) {
            self::processError($e, "Could not get Grocy local time");
        }

        if (isset($result["time_local_sqlite3"])) {
            return $result["time_local_sqlite3"];
        }
        self::logError("Grocy did not provide local time");
        return "0";
    }


    /**
     * Open product with $id
     *
     * @param String productId
     */
    public static function openProduct($id) {

        $data   = json_encode(array(
            'amount' => "1"
        ));
        $apiurl = API_STOCK . "/" . $id . "/open";

        $curl = new CurlGenerator($apiurl, METHOD_POST, $data);
        try {
            $curl->execute();
        } catch (Exception $e) {
            self::processError($e, "Could not open Grocy product");
        }
    }


    /**
     *   Check if API details are correct
     *
     * @param String $givenurl URL to Grocy API
     * @param String $apikey API key
     *
     * @return String | true Returns String with error or true if connection could be established
     */
    public static function checkApiConnection(string $givenurl, string $apikey) {
        $loginInfo = array(LOGIN_URL => $givenurl, LOGIN_API_KEY => $apikey);

        $curl = new CurlGenerator(API_SYTEM_INFO, METHOD_GET, null, $loginInfo);
        try {
            $result = $curl->execute(true);
        } catch (InvalidServerResponseException $e) {
            return "Could not connect to server<br>";
        } catch (InvalidJsonResponseException $e) {
            return "Error: " . $e->getMessage();
        } catch (UnauthorizedException $e) {
            return "Invalid API key<br>";
        } catch (InvalidSSLException $e) {
            return "Invalid SSL certificate!<br>If you are using a self-signed certificate, you can disable the check in config.php<br>";
        } catch (InvalidParameterException $e) {
            return "Internal error: Invalid parameter passed<br>";
        } catch (NotFoundException $e) {
            return "Path not found - check if correct URL was entered<br>";
        } catch (LimitExceededException $e) {
            return "Connection limits exceeded<br>";
        } catch (InternalServerErrorException $e) {
            return "Grocy reported internal error.<br>";
        }
        if (isset($result["grocy_version"]["Version"])) {
            $version = $result["grocy_version"]["Version"];

            if (!API::isSupportedGrocyVersion($version)) {
                return "Grocy " . MIN_GROCY_VERSION . " or newer required. You are running " . $version . ", please upgrade your Grocy instance.<br>";
            } else {
                return true;
            }
        }
        return "Invalid response. Are you using the correct URL?<br>";
    }

    /**
     *
     * Check if the installed Grocy version is equal or newer to the required version
     *
     * @param String reported Grocy version
     * @return boolean true if version supported
     */
    public static function isSupportedGrocyVersion($version): bool {
        if (!preg_match("/\d+.\d+.\d+/", $version)) {
            return false;
        }

        $version_ex    = explode(".", $version);
        $minVersion_ex = explode(".", MIN_GROCY_VERSION);

        if ($version_ex[0] < $minVersion_ex[0]) {
            return false;
        } else if ($version_ex[0] == $minVersion_ex[0] && $version_ex[1] < $minVersion_ex[1]) {
            return false;
        } else if ($version_ex[0] == $minVersion_ex[0] && $version_ex[1] == $minVersion_ex[1] && $version_ex[2] < $minVersion_ex[2]) {
            return false;
        } else {
            return true;
        }
    }


    /**
     *
     * Requests the version of the Grocy instance
     *
     * @return String Reported Grocy version
     */
    public static function getGrocyVersion(): ?string {

        $curl = new CurlGenerator(API_SYTEM_INFO);
        try {
            $result = $curl->execute(true);
        } catch (Exception $e) {
            self::processError($e, "Could not lookup Grocy version");
        }

        if (isset($result["grocy_version"]["Version"])) {
            return $result["grocy_version"]["Version"];
        }
        self::logError("Grocy did not provide version number");
        return null;
    }


    /**
     *
     *  Adds a Grocy product.
     *
     * @param $id
     * @param $amount
     * @param null $bestbefore
     * @param null $price
     * @param null $fileLock
     * @param null $defaultBestBefore
     * @return false if default best before date not set
     */
    public static function purchaseProduct($id, $amount, $bestbefore = null, $price = null, &$fileLock = null, $defaultBestBefore = null): bool {
        $data = array(
            'amount'           => $amount,
            'transaction_type' => 'purchase'
        );

        if ($price != null) {
            $data['price'] = $price;
        }

        if ($bestbefore != null) {
            $daysBestBefore = $bestbefore;
        } else {
            if ($defaultBestBefore != null)
                $daysBestBefore = $defaultBestBefore;
            else
                $daysBestBefore = self::getDefaultBestBeforeDays($id);
        }
        $data['best_before_date'] = self::formatBestBeforeDays($daysBestBefore);
        $data_json                = json_encode($data);
        $apiurl                   = API_STOCK . "/" . $id . "/add";

        $curl = new CurlGenerator($apiurl, METHOD_POST, $data_json);
        try {
            $curl->execute();
        } catch (Exception $e) {
            self::processError($e, "Could not add product to inventory");
        }
        if ($fileLock != null)
            $fileLock->removeLock();
        if (BBConfig::getInstance()["SHOPPINGLIST_REMOVE"]) {
            self::removeFromShoppinglist($id, $amount);
        }
        return ($daysBestBefore != 0);
    }


    /**
     *
     * Removes an item from the default shoppinglist
     *
     * @param String product id
     * @param Int amount
     */
    public static function removeFromShoppinglist($productid, $amount) {
        $data   = json_encode(array(
            'product_id'     => $productid,
            'product_amount' => $amount
        ));
        $apiurl = API_SHOPPINGLIST . "remove-product";

        $curl = new CurlGenerator($apiurl, METHOD_POST, $data);
        try {
            $curl->execute();
        } catch (Exception $e) {
            self::processError($e, "Could not remove item from shoppinglist");
        }
    }


    /**
     *
     * Adds an item to the default shoppinglist
     *
     * @param String product id
     * @param Int amount
     */
    public static function addToShoppinglist($productid, $amount) {
        $data   = json_encode(array(
            'product_id'     => $productid,
            'product_amount' => $amount
        ));
        $apiurl = API_SHOPPINGLIST . "add-product";

        $curl = new CurlGenerator($apiurl, METHOD_POST, $data);
        try {
            $curl->execute();
        } catch (Exception $e) {
            self::processError($e, "Could not add item to shoppinglist");
        }
    }


    /**
     * Consumes a product
     *
     * @param int id
     * @param int amount
     * @param boolean set true if product was spoiled. Default: false
     */
    public static function consumeProduct($id, $amount, $spoiled = false) {

        $data = json_encode(array(
            'amount'           => $amount,
            'transaction_type' => 'consume',
            'spoiled'          => $spoiled
        ));

        $apiurl = API_STOCK . "/" . $id . "/consume";

        $curl = new CurlGenerator($apiurl, METHOD_POST, $data);
        try {
            $curl->execute();
        } catch (Exception $e) {
            self::processError($e, "Could not consume product");
        }
    }

    /**
     * Sets barcode to a Grocy product (unlike in the old API, this does not
     * replace previous barcodes)
     * @param int product id
     * @param string  barcode to be set
     */
    public static function addBarcode($id, $barcode) {

        $data = json_encode(array(
            "product_id" => $id,
            "barcode"    => $barcode
        ));

        $curl = new CurlGenerator(API_O_BARCODES, METHOD_POST, $data);
        try {
            $curl->execute();
        } catch (Exception $e) {
            self::processError($e, "Could not set Grocy barcode");
        }
        RedisConnection::expireAllBarcodes();
    }


    /**
     * Formats the amount of days into future date
     * @param int $days Amount of days a product is consumable, or -1 if it does not expire
     * @return false|string  Formatted date
     */
    private static function formatBestBeforeDays(int $days) {
        if ($days == "-1") {
            return "2999-12-31";
        } else {
            $date = date("Y-m-d");
            return date('Y-m-d', strtotime($date . " + $days days"));
        }
    }

    /**
     * Retrieves the default best before date for a product
     * @param int $id Product id
     * @return int     Amount of days or -1 if it does not expire
     */
    private static function getDefaultBestBeforeDays(int $id): int {
        $info = self::getProductInfo($id);
        if ($info == null)
            return 0;
        $days = $info->defaultBestBeforeDays;
        checkIfNumeric($days);
        return $days;
    }


    /**
     * Get a Grocy product by barcode, is able to cache
     * @param string $barcode barcode to lookup
     * @param bool $ignoreCache Cache will be ignored if true
     * @return GrocyProduct|null Product info or null if barcode is not associated with a product
     */
    public static function getProductByBarcode(string $barcode, $ignoreCache = false): ?GrocyProduct {
        $allBarcodes = self::getAllBarcodes($ignoreCache);
        if (!isset($allBarcodes[$barcode])) {
            return null;
        } else {
            return self::getProductInfo($allBarcodes[$barcode]);
        }
    }


    /**
     * Get a Grocy product by barcode from API, currently unused
     * @param string $barcode barcode to lookup
     * @return GrocyProduct|null Product info or null if barcode is not associated with a product
     */
    public static function getProductByBarcodeLegacy(string $barcode): ?GrocyProduct {
        $apiurl = API_STOCK_BY_BARCODE . $barcode;
        $curl   = new CurlGenerator($apiurl);
        try {
            $result = $curl->execute(true);
        } catch (Exception $e) {
            self::processError($e, "Could not lookup Grocy barcode");
        }

        if (isset($result["product"]["id"])) {
            return GrocyProduct::parseProductInfoStock($result);
        }
        return null;
    }

    /**
     * @param bool $ignoreCache If true, cache will be ignored and afterwards updated.
     * @return array|null
     */
    public static function getAllBarcodes($ignoreCache = false): ?array {
        $updateRedis = false;
        if (RedisConnection::isRedisAvailable()) {
            if (!$ignoreCache && RedisConnection::isCacheAvailable()) {
                $cachedBarcodes = RedisConnection::getAllBarcodes();
                if ($cachedBarcodes != null)
                    return $cachedBarcodes;
                else
                    $updateRedis = true;
            } else {
                $updateRedis = true;
            }
        }

        $curl = new CurlGenerator(API_O_BARCODES);
        try {
            $curlResult = $curl->execute(true);
        } catch (Exception $e) {
            self::processError($e, "Could not lookup Grocy barcodes");
            return null;
        }
        $result = array();
        foreach ($curlResult as $item) {
            if (!isset($item["barcode"]) || !isset($item["product_id"]))
                continue;
            $result[$item["barcode"]] = $item["product_id"];
        }
        if ($updateRedis)
            RedisConnection::cacheAllBarcodes($result);
        return $result;
    }


    /**
     * Gets location and amount of stock of a product
     * @param int $productid Product id
     * @return null|array              Array with location info, null if none in stock
     */
    public static function getProductLocations(int $productid): ?array {

        $apiurl = API_STOCK . "/" . $productid . "/locations";

        $result = null;
        $curl   = new CurlGenerator($apiurl);
        try {
            $result = $curl->execute(true);
        } catch (Exception $e) {
            self::processError($e, "Could not lookup product location");
        }
        return $result;
    }


    /**
     * Getting info of a Grocy chore
     * @param string $choreId Chore ID.
     * @return null|array       Either chore if ID, or all chores
     */
    public static function getChoreInfo(string $choreId): ?array {
        $apiurl = API_CHORES . "/" . $choreId;
        $curl   = new CurlGenerator($apiurl);
        try {
            $result = $curl->execute(true);
        } catch (Exception $e) {
            self::processError($e, "Could not get chore info");
            return null;
        }
        return $result;
    }


    /**
     * Getting info of all Grocy chores
     * @return array|null       Array with all chore infos or null
     */
    public static function getAllChoresInfo(): ?array {
        $apiurl = API_CHORES;
        $curl   = new CurlGenerator($apiurl);
        try {
            $result = $curl->execute(true);
        } catch (Exception $e) {
            self::processError($e, "Could not get chore info");
            return null;
        }
        return $result;
    }


    /**
     * Executes a Grocy chore
     * @param  [int] $choreId Chore id
     */
    public static function executeChore($choreId) {

        $apiurl = API_CHORE_EXECUTE . $choreId . "/execute";
        $data   = json_encode(array(
            'tracked_time' => "",
            'done_by'      => ""
        ));


        $curl = new CurlGenerator($apiurl, METHOD_POST, $data);
        try {
            $result = $curl->execute(true);
        } catch (Exception $e) {
            self::processError($e, "Could not execute chore");
        }
    }

    public static function processError($e, $errorMessage) {
        $class = get_class($e);
        switch ($class) {
            case 'InvalidServerResponseException':
                self::logError("Could not connect to Grocy server: " . $errorMessage);
                break;
            case 'UnauthorizedException':
                self::logError("Invalid API key: " . $errorMessage);
                break;
            case 'InvalidJsonResponseException':
                self::logError("Invalid JSON: " . $errorMessage . " " . $e->getMessage());
                break;
            case 'InvalidSSLException':
                self::logError("Invalid SSL certificate: " . $errorMessage);
                break;
            case 'InvalidParameterException':
                self::logError("Internal error: Invalid parameter passed: " . $errorMessage);
                break;
            case 'NotFoundException':
                self::logError("Server reported path not found: " . $errorMessage);
                break;
            case 'LimitExceededException':
                self::logError("Connection limits exceeded: " . $errorMessage);
                break;
            case 'InternalServerErrorException':
                self::logError("Grocy reported internal error: " . $errorMessage);
                break;
        }
    }

    public static function logError($errorMessage, $isFatal = true) {
        try {
            DatabaseConnection::getInstance()->saveError($errorMessage, $isFatal);
        } catch (DbConnectionDuringEstablishException $_) {
            // Error occurred during the DB connection. As such, DB is not available to log the error.
        }
    }

    public static function runBenchmark($id) {
        $randomBarcode = "rand" . rand(10, 10000);
        echo "Running benchmark with product ID $id:\n\n";
        $timeStart = microtime(true);
        self::benchmarkApiCall("getAllProductsInfo", true);
        self::benchmarkApiCall("getAllProductsInfo");
        self::benchmarkApiCall("getProductInfo", $id);
        self::benchmarkApiCall("openProduct", $id);
        self::benchmarkApiCall("getGrocyVersion");
        self::benchmarkApiCall("purchaseProduct", $id, 1);
        self::benchmarkApiCall("addToShoppinglist", $id, 1);
        self::benchmarkApiCall("removeFromShoppinglist", $id, 1);
        self::benchmarkApiCall("consumeProduct", $id, 1);
        self::benchmarkApiCall("addBarcode", $id, $randomBarcode);
        self::benchmarkApiCall("getAllBarcodes", true);
        self::benchmarkApiCall("getAllBarcodes");
        self::benchmarkApiCall("getProductByBarcode", $randomBarcode, true);
        self::benchmarkApiCall("getProductByBarcode", $randomBarcode);
        self::benchmarkApiCall("getProductLocations", $id);
        self::benchmarkApiCall("getAllChoresInfo");
        $timeTotal = round((microtime(true) - $timeStart) * 1000);
        echo "\nTotal time: $timeTotal ms\n";
        die();
    }

    private static function benchmarkApiCall($apiCall, ...$param) {
        $timeStart = microtime(true);
        $name      = "$apiCall(";
        foreach ($param as $parameter) {
            $name = $name . $parameter . ", ";
        }
        $name = rtrim($name, ", ") . ")";
        switch (sizeof($param)) {
            case 0:
                call_user_func("API::" . $apiCall);
                break;
            case 1:
                call_user_func("API::" . $apiCall, $param[0]);
                break;
            case 2:
                call_user_func("API::" . $apiCall, $param[0], $param[1]);
                break;
        }
        $timeTotal = round((microtime(true) - $timeStart) * 1000);
        echo "Running $name took $timeTotal ms.\n";
    }
}
