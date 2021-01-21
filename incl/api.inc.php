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

const MIN_GROCY_VERSION = "3.0.1";

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

    public static function parseProductInfoStock(array $infoArray): GrocyProduct {
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


    public static function parseProductInfoObjects(array $infoArray): GrocyProduct {
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

class ApiInternalErrorException extends Exception {
}

class API {

    /**
     * Getting info all Grocy products.
     *
     * @param bool $ignoreCache If true, cache will be ignored and afterwards updated.
     * @return GrocyProduct[]|null Array of products
     */
    public static function getAllProductsInfo(bool $ignoreCache = false): ?array {
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
                $grocyProduct                = GrocyProduct::parseProductInfoObjects($product);
                $products[$grocyProduct->id] = $grocyProduct;
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
     * @param int ProductId
     * @return GrocyProduct Product info or array of products
     */
    public static function getProductInfo(int $productId): ?GrocyProduct {
        $url = API_STOCK_PRODUCTS . "/" . $productId;

        $result = null;  // Assure assignment in event curl throws exception.
        $curl   = new CurlGenerator($url);
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
        $url  = API_SYTEM_TIME . "?offset=" . $offset;
        $curl = new CurlGenerator($url);
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
     * @param int productId
     */
    public static function openProduct(int $id) {

        $data = json_encode(array(
            'amount' => "1"
        ));
        $url  = API_STOCK . "/" . $id . "/open";

        $curl = new CurlGenerator($url, METHOD_POST, $data);
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
     * @throws DbConnectionDuringEstablishException
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
    public static function isSupportedGrocyVersion(string $version): bool {
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
     * @param int $id
     * @param int $amount
     * @param string|null $bestbefore
     * @param string|null $price
     * @param LockGenerator|null $fileLock
     * @param string|null $defaultBestBefore
     * @return bool if default best before date not set
     * @throws Exception
     */
    public static function purchaseProduct(int $id, int $amount, string $bestbefore = null, string $price = null, LockGenerator &$fileLock = null, string $defaultBestBefore = null): bool {
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
        $url                      = API_STOCK . "/" . $id . "/add";

        $curl = new CurlGenerator($url, METHOD_POST, $data_json);
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
     * @param int $productid
     * @param int $amount
     */
    public static function removeFromShoppinglist(int $productid, int $amount) {
        $data = json_encode(array(
            'product_id'     => $productid,
            'product_amount' => $amount
        ));
        $url  = API_SHOPPINGLIST . "remove-product";

        $curl = new CurlGenerator($url, METHOD_POST, $data);
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
     * @param int $productid
     * @param int $amount
     */
    public static function addToShoppinglist(int $productid, int $amount) {
        $data = json_encode(array(
            'product_id'     => $productid,
            'product_amount' => $amount
        ));
        $url  = API_SHOPPINGLIST . "add-product";

        $curl = new CurlGenerator($url, METHOD_POST, $data);
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
    public static function consumeProduct(int $id, int $amount, bool $spoiled = false) {
        if ($amount <= 0)
            return;

        $data = json_encode(array(
            'amount'           => $amount,
            'transaction_type' => 'consume',
            'spoiled'          => $spoiled
        ));

        $url = API_STOCK . "/" . $id . "/consume";

        $curl = new CurlGenerator($url, METHOD_POST, $data);
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
    public static function addBarcode(int $id, string $barcode) {

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
     * Adds quantity information from grocy barcode
     * @param string $barcode Barcode to modify
     * @param int $quantity
     * @param bool $ignoreCache
     */
    public static function addBarcodeQuantity(string $barcode, int $quantity, bool $ignoreCache = false) {

        $data = json_encode(array(
            "amount" => $quantity,
        ));

        $barcodes = self::getAllBarcodes($ignoreCache);
        if (!isset($barcodes[$barcode])) {
            if (!$ignoreCache) {
                self::addBarcodeQuantity($barcode, $quantity, true);
            } else {
                self::processError(new ApiInternalErrorException(), "Could not add quantity, barcode $barcode not registered with Grocy.");
                return;
            }
        }
        $barcodeId = $barcodes[$barcode]["barcode_id"];
        $url       = API_O_BARCODES . "/" . $barcodeId;

        $curl = new CurlGenerator($url, METHOD_PUT, $data);
        try {
            $curl->execute();
        } catch (Exception $e) {
            self::processError($e, "Could not set Grocy barcode quantity");
        }
        RedisConnection::expireAllBarcodes();
    }


    /**
     * Delete quantity information from grocy barcode
     * @param string $id
     */
    public static function deleteBarcodeQuantity(string $id) {
        $data = json_encode(array(
            "amount" => null
        ));
        $url  = API_O_BARCODES . "/" . $id;

        $curl = new CurlGenerator($url, METHOD_PUT, $data);
        try {
            $curl->execute();
        } catch (Exception $e) {
            self::processError($e, "Could not remove Grocy barcode quantity");
        }
        RedisConnection::expireAllBarcodes();
    }


    /**
     * Formats the amount of days into future date
     * @param int $days Amount of days a product is consumable, or -1 if it does not expire
     * @return false|string  Formatted date
     */
    private static function formatBestBeforeDays(int $days) {
        if ($days == -1) {
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
            return self::getProductInfo($allBarcodes[$barcode]["id"]);
        }
    }


    /**
     * @param bool $ignoreCache If true, cache will be ignored and afterwards updated.
     * @return array|null
     */
    public static function getAllBarcodes(bool $ignoreCache = false): ?array {
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
            $result[$item["barcode"]]["id"]         = $item["product_id"];
            $result[$item["barcode"]]["factor"]     = $item["amount"];
            $result[$item["barcode"]]["barcode_id"] = $item["id"];
            $result[$item["barcode"]]["barcode"]    = $item["barcode"];
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

        $url = API_STOCK . "/" . $productid . "/locations";

        $result = null;
        $curl   = new CurlGenerator($url);
        try {
            $result = $curl->execute(true);
        } catch (Exception $e) {
            self::processError($e, "Could not lookup product location");
        }
        return $result;
    }


    /**
     * Getting info of a Grocy chore
     * @param int $choreId Chore ID.
     * @return null|array       Either chore if ID, or all chores
     */
    public static function getChoreInfo(int $choreId): ?array {
        $url  = API_CHORES . "/" . $choreId;
        $curl = new CurlGenerator($url);
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
        $url  = API_CHORES;
        $curl = new CurlGenerator($url);
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
     * @param int $choreId
     */
    public static function executeChore(int $choreId) {

        $url  = API_CHORE_EXECUTE . $choreId . "/execute";
        $data = json_encode(array(
            'tracked_time' => "",
            'done_by'      => ""
        ));


        $curl = new CurlGenerator($url, METHOD_POST, $data);
        try {
            $curl->execute(true);
        } catch (Exception $e) {
            self::processError($e, "Could not execute chore");
        }
    }

    /**
     * @param Exception $e
     * @param string $errorMessage
     */
    public static function processError(Exception $e, string $errorMessage) {
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
            case 'ApiInternalErrorException':
                self::logError("Could not process API call: " . $errorMessage);
                break;
        }
    }

    /**
     * @param string $errorMessage
     * @param bool $isFatal
     */
    public static function logError(string $errorMessage, bool $isFatal = true) {
        try {
            DatabaseConnection::getInstance()->saveError($errorMessage, $isFatal);
        } catch (DbConnectionDuringEstablishException $_) {
            // Error occurred during the DB connection. As such, DB is not available to log the error.
        }
    }

    /**
     * Runs a benchmark for debugging purposes
     * @param int $id Product ID of grocy product to benchmark
     */
    public static function runBenchmark(int $id) {
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

    private static function benchmarkApiCall(string $apiCall, ...$param) {
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
