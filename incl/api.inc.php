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
const API_O_QU_CONV_R      = 'objects/quantity_unit_conversions_resolved';
const API_STOCK_PRODUCTS   = 'stock/products';
const API_ALL_PRODUCTS     = 'stock';
const API_SHOPPINGLIST     = 'stock/shoppinglist/';
const API_CHORES           = 'objects/chores';
const API_STOCK            = 'stock/products';
const API_STOCK_BY_BARCODE = 'stock/products/by-barcode/';
const API_CHORE_EXECUTE    = 'chores/';
const API_SYTEM_INFO       = 'system/info';
const API_SYTEM_TIME       = 'system/time';

const MIN_GROCY_VERSION = "4.0.3";

const LOGIN_URL     = "loginurl";
const LOGIN_API_KEY = "loginkey";

const DISPLAY_DEBUG = false;


class GrocyProduct {
    public int $id;
    public string $name;
    public ?array $barcodes = null;
    public float $stockAmount = 0;
    public bool $isTare = false;
    public string $tareWeight;
    public GrocyQuantityUnit $unit;
    public GrocyQuantityUnit $purchaseUnit;
    public GrocyQuantityUnit $consumeUnit;
    public ?GrocyQuantityUnit $priceUnit = null;
    public ?float $purchaseUnitFactor = null;
    public ?float $priceUnitFactor = null;
    public string $defaultBestBeforeDays;
    public string $creationDate;
    protected ?array $productInfo = null;
    protected ?array $stockInfo = null;

    public static function parseProductInfoStock(array $infoArray): GrocyProduct {
        $result = new GrocyProduct();
        $result->parseFromInfo($infoArray["product"]);
        $result->parseFromStock($infoArray);
        return $result;
    }

    public function parseFromStock(array $infoArray): void {
        $this->stockInfo   = $infoArray;
        $this->stockAmount = checkIfNumeric($infoArray["stock_amount"]);
        $this->barcodes    = GrocyProductBarcode::parseProductBarcodes($infoArray["product_barcodes"]);

        if (isset($infoArray["quantity_unit_stock"]))
            $this->unit = GrocyQuantityUnit::parseQuantityUnit($infoArray["quantity_unit_stock"]);

        if (isset($infoArray["default_quantity_unit_purchase"]))
            $this->purchaseUnit = GrocyQuantityUnit::parseQuantityUnit($infoArray["default_quantity_unit_purchase"]);

        if (isset($infoArray["default_quantity_unit_consume"]))
            $this->consumeUnit = GrocyQuantityUnit::parseQuantityUnit($infoArray["default_quantity_unit_consume"]);

        if (isset($infoArray["quantity_unit_price"]))
            $this->priceUnit = GrocyQuantityUnit::parseQuantityUnit($infoArray["quantity_unit_price"]);

        if (isset($infoArray["qu_conversion_factor_purchase_to_stock"]))
            $this->purchaseUnitFactor = checkIfFloat($infoArray["qu_conversion_factor_purchase_to_stock"]);

        if (isset($infoArray["qu_conversion_factor_price_to_stock"]))
            $this->priceUnitFactor = checkIfFloat($infoArray["qu_conversion_factor_price_to_stock"]);
    }

    public static function parseProductInfo(array $infoArray): GrocyProduct {
        $result = new GrocyProduct();
        $result->parseFromInfo($infoArray);
        return $result;
    }

    public function parseFromInfo(array $infoArray): void {
        $this->productInfo           = $infoArray;
        $this->id                    = checkIfNumeric($infoArray["id"]);
        $this->name                  = sanitizeString($infoArray["name"]);
        $this->isTare                = ($infoArray["enable_tare_weight_handling"] == "1");
        $this->tareWeight            = sanitizeString($infoArray["tare_weight"]);
        $this->defaultBestBeforeDays = $infoArray["default_best_before_days"];
        $this->creationDate          = $infoArray["row_created_timestamp"];

        $this->unit = new GrocyQuantityUnit($infoArray["qu_id_stock"]);
        $this->purchaseUnit = new GrocyQuantityUnit($infoArray["qu_id_purchase"]);
        $this->consumeUnit = new GrocyQuantityUnit($infoArray["qu_id_consume"]);
        $this->priceUnit = new GrocyQuantityUnit($infoArray["qu_id_price"]);

        if (isset($infoArray["qu_conversion_factor_purchase_to_stock"]))
            $this->purchaseUnitFactor = checkIfFloat($infoArray["qu_conversion_factor_purchase_to_stock"]);

        if (isset($infoArray["qu_conversion_factor_price_to_stock"]))
            $this->priceUnitFactor = checkIfFloat($infoArray["qu_conversion_factor_price_to_stock"]);
    }

    public function updateStock(bool $ignoreCache = false): bool {
        $productInfo = getProductInfo($this->id);
        if ($productInfo == null)
            return false;

        if ($ignoreCache || $this->productInfo == null)
            $this->parseFromInfo($productInfo->productInfo);
        $this->parseFromStock($productInfo->stockInfo);

        return true;
    }

    public function updateBarcodes(bool $ignoreCache = false): bool {
        $allBarcodes = getAllBarcodes($ignoreCache);
        $this->barcodes = array();
        foreach ($allBarcodes as $barcodeInfo) {
            if (!($barcodeInfo instanceof GrocyProductBarcode) || $barcodeInfo->productId != $this->id)
                continue;
            $this->barcodes[$barcodeInfo->barcode] = $barcodeInfo;
        }
        $foundBarcodes = sizeof($this->barcodes) > 0;
        if ($ignoreCache && !$foundBarcodes)
            return $this->updateStock();

        return $foundBarcodes;
    }

    public function getBarcodeInfo(string $barcode): ?GrocyProductBarcode {
        $hasBarcodes = $this->barcodes != null && sizeof($this->barcodes) > 0;
        if (!$hasBarcodes) {
            if (!$this->updateBarcodes($this->barcodes != null))
                return null;
        }
      return $this->barcodes[$barcode] ?? null;
    }

    public function getQuantityUnitByBarcode(?GrocyProductBarcode $barcode, bool $isConsume = false): GrocyQuantityUnit {
        if ($barcode != null)
            return new GrocyQuantityUnit($barcode->quId);

        $qu = $isConsume ? $this->consumeUnit : $this->purchaseUnit;
        return $qu ?? $this->unit;
    }

    public function getAmountByBarcode(string $barcode, bool $isConsume = false, ?GrocyQuantityUnit $qu = null, ?float $quantity = null): ?float {
        $barcodeInfo = $this->getBarcodeInfo($barcode);
        $qu = $qu ?? $this->getQuantityUnitByBarcode($barcodeInfo, $isConsume);
        $quantity = $quantity ?? $barcodeInfo->amount;
        $quc = $this->getQuantityConversionFrom($qu->id);
        if ($quc == null)
            return null;
        return $quc->factor * $quantity;
    }

    public function getQuantityConversionFrom(int $quId): ?GrocyQuantityConversion {
        $factor = null;
        if ($quId == $this->unit->id)
            $factor = 1;
        else if ($quId == $this->purchaseUnit->id)
            $factor = $this->purchaseUnitFactor;
        else if ($quId == $this->priceUnit->id)
            $factor = $this->priceUnitFactor;

        if ($factor != null) {
            $quc = new GrocyQuantityConversion();
            $quc->productId = $this->id;
            $quc->fromQuId  = $quId;
            $quc->toQuId    = $this->unit->id;
            $quc->factor    = $factor;
            return $quc;
        }

        return API::getQuantityConversion($this->id, $quId, $this->unit->id);
    }

    public static function parseQuantityConversion(array $convArray): GrocyProduct {
        $result            = new GrocyQuantityConversion();
        $result->id        = checkIfNumeric($convArray["id"]);
        $result->productId = checkIfNumeric($convArray["product_id"]);
        $result->fromQuId  = checkIfNumeric($convArray["from_qu_id"]);
        $result->toQuId    = checkIfNumeric($convArray["to_qu_id"]);
        $result->factor    = checkIfFloat($convArray["factor"]);

        return $result;
    }

    public function getQuantityConversionByBarcode(string $barcode, bool $isConsume = false): ?GrocyQuantityConversion {
        $qu = $this->getQuantityUnitByBarcode($isConsume);
        return $this->getQuantityConversionFrom($qu->id);
    }
}

class GrocyBarcode {
    public int $id;
    public string $barcode;
    public ?string $note = null;
    protected ?array $barcodeInfo = null;

    public function __construct(string|int $id = -1) {
        $this->id = checkIfNumeric($id);
    }

    public static function parseBarcode(array $barcodeArray): GrocyBarcode {
        $result = new GrocyBarcode($barcodeArray["id"]);
        $result->parseFromBarcode($barcodeArray);
        return $result;
    }

    public static function parseAnyBarcode(array $barcodeArray): GrocyBarcode {
        if (isset($barcodeArray["product_id"]) && isset($barcodeArray["amount"]))
            return GrocyProductBarcode::parseProductBarcode($barcodeArray);
        else
            return GrocyBarcode::parseBarcode($barcodeArray);
    }

    public function parseFromBarcode(array $barcodeArray): void {
        $this->barcodeInfo = $barcodeArray;
        $this->id          = checkIfNumeric($barcodeArray["id"]);
        $this->barcode     = sanitizeString($barcodeArray["barcode"]);
        $this->note        = sanitizeString($barcodeArray["note"]);
    }

    public static function parseBarcodes(array $barcodes): array {
        $result = array();
        foreach ($barcodes as $barcodeArray) {
            $barcodeInfo = GrocyBarcode::parseBarcode($barcodeArray);
            $result[$barcodeInfo->barcode] = $barcodeInfo;
        }
        return $result;
    }

    public static function parseAnyBarcodes(array $barcodes): array {
        $result = array();
        foreach ($barcodes as $barcodeArray) {
            $barcodeInfo = GrocyBarcode::parseAnyBarcode($barcodeArray);
            $result[$barcodeInfo->barcode] = $barcodeInfo;
        }
        return $result;
    }
}

class GrocyProductBarcode extends GrocyBarcode {
    public int $productId;
    public int $quId;
    public float $amount;
    public ?int $shoppingLocationId = null;
    public ?float $lastPrice = null;

    public function __construct(string|int $id = -1) {
        parent::__construct($id);
    }

    public static function parseProductBarcode(array $barcodeArray): GrocyProductBarcode {
        $result = new GrocyProductBarcode($barcodeArray["id"]);
        $result->parseFromProductBarcode($barcodeArray);
        return $result;
    }

    public function parseFromProductBarcode(array $barcodeArray): void {
        $this->parseFromBarcode($barcodeArray);
        $this->productId = checkIfNumeric($barcodeArray["product_id"]);
        $this->quId      = checkIfNumeric($barcodeArray["qu_id"]);
        $this->amount    = checkIfFloat($barcodeArray["amount"]);

        if (isset($barcodeArray["shopping_location_id"]))
            $this->shoppingLocationId = checkIfNumeric($barcodeArray["shopping_location_id"]);
        if (isset($barcodeArray["last_price"]))
            $this->lastPrice = checkIfFloat($barcodeArray["last_price"]);
    }

    public static function parseProductBarcodes(array $barcodes): array {
        $result = array();
        foreach ($barcodes as $barcodeArray) {
            $barcodeInfo = GrocyProductBarcode::parseProductBarcode($barcodeArray);
            $result[$barcodeInfo->barcode] = $barcodeInfo;
        }
        return $result;
    }
}

class GrocyQuantityUnit {
    public int $id;
    public string $name;
    public ?string $description;
    public ?string $namePlural;
    public ?array $pluralForms;
    public bool $isActive;

    public function __construct(string|int $id) {
        $this->id = checkIfNumeric($id);
    }

    public static function parseQuantityUnit(array $convArray): GrocyQuantityUnit {
        $result              = new GrocyQuantityUnit($convArray["id"]);
        $result->name        = sanitizeString($convArray["name"]);
        $result->namePlural  = sanitizeString($convArray["name_plural"] ?? null);
        $result->description = sanitizeString($convArray["description"]);
        $result->pluralForms = $convArray["plural_forms"];
        $result->isActive    = ($convArray["active"] == 1);

        return $result;
    }
}

class GrocyQuantityConversion {
    public int $id;
    public int $productId;
    public int $fromQuId;
    public int $toQuId;
    public float $factor;

    public function __construct(string|int $id = -1) {
        $this->id = checkIfNumeric($id);
    }

    public static function parseQuantityConversion(array $convArray): GrocyQuantityConversion {
        $result            = new GrocyQuantityConversion($convArray["id"]);
        $result->productId = checkIfNumeric($convArray["product_id"]);
        $result->fromQuId  = checkIfNumeric($convArray["from_qu_id"]);
        $result->toQuId    = checkIfNumeric($convArray["to_qu_id"]);
        $result->factor    = checkIfFloat($convArray["factor"]);

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
                $grocyProduct                = GrocyProduct::parseProductInfo($product);
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

    public static function getQuantityConversion(int $productId, int $fromQuId, int $toQuId): ?GrocyQuantityConversion {
        $url = API_O_QU_CONV_R . "?query[]=product_id=" . $productId
            . "&query[]=from_qu_id=" . $fromQuId
            . "&query[]=to_qu_id=" . $toQuId;
        $result = null;  // Assure assignment in event curl throws exception.
        $curl   = new CurlGenerator($url);
        try {
            $result = $curl->execute(true);
        } catch (Exception $e) {
            self::processError($e, "Could not lookup Grocy quantity conversion");
            return null;
        }
        if ($result == null || sizeof($result) == 0) {
            $log = new LogOutput("Failed to look up quantity conversion for product_id=$productId from qu=$fromQuId to qu=$toQuId", EVENT_TYPE_ERROR);
            $log->setVerbose()->dontSendWebsocket()->createLog();
            return null;
        }
        return GrocyQuantityConversion::parseQuantityConversion($result[0]);
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
     * @param float amount
     *
     * @return void
     */
    public static function openProduct(int $id, float $amount): void {

        $data = json_encode(array(
            'amount' => $amount
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
     * @param float $amount
     * @param string|null $bestbefore
     * @param string|null $price
     * @param LockGenerator|null $fileLock
     * @param string|null $defaultBestBefore
     * @return bool if default best before date not set
     * @throws Exception
     */
    public static function purchaseProduct(int $id, float $amount, string $bestbefore = null, string $price = null, LockGenerator &$fileLock = null, string $defaultBestBefore = null): bool {
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
     * Removes an item from the default shoppinglist
     *
     * @param int $productid
     * @param float $amount
     *
     * @return void
     */
    public static function removeFromShoppinglist(int $productid, float $amount): void {
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
     * Adds an item to the default shoppinglist
     *
     * @param int $productid
     * @param float $amount
     *
     * @return void
     */
    public static function addToShoppinglist(int $productid, float $amount): void {
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
     * @param float amount
     * @param boolean set true if product was spoiled. Default: false
     *
     * @return void
     */
    public static function consumeProduct(int $id, float $amount, bool $spoiled = false) {
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
     *
     * @param int product id
     * @param string  barcode to be set
     * @param string|null note for barcode
     *
     * @return void
     */
    public static function addBarcode(int $id, string $barcode, ?string $note): void {

        if ($note == "" || $note == "N/A")
            $note = null;

        $data = json_encode(array(
            "product_id" => $id,
            "barcode"    => $barcode,
            "note"       => $note
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
     *
     * @param string $barcode Barcode to modify
     * @param float $quantity
     * @param bool $ignoreCache
     *
     * @return void
     */
    public static function addBarcodeQuantity(string $barcode, float $quantity, bool $ignoreCache = false) {

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
        $barcodeId = $barcodes[$barcode]->id;
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
     *
     * @param string $id
     *
     * @return void
     */
    public static function deleteBarcodeQuantity(string $id): void {
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
        return checkIfNumeric($days);
    }


    /**
     * Get a Grocy product by barcode, is able to cache
     * @param string $barcode barcode to lookup
     * @param bool $ignoreCache Cache will be ignored if true
     * @return GrocyProduct|null Product info or null if barcode is not associated with a product
     */
    public static function getProductByBarcode(string $barcode, bool $ignoreCache = false): ?GrocyProduct {
        $grocyCode = self::getProductIdFromGrocyCode($barcode);
        if ($grocyCode != null)
            return self::getProductInfo($grocyCode);
        if (stringStartsWith($barcode, "GRCY:P:")) {
            $id = str_replace("GRCY:P:", "", $barcode);
            return self::getProductInfo(checkIfNumeric($id));
        }
        $allBarcodes = self::getAllBarcodes($ignoreCache);
        if (!isset($allBarcodes[$barcode]) || !($allBarcodes[$barcode] instanceof GrocyProductBarcode)) {
            return null;
        } else {
            $productId = $allBarcodes[$barcode]->productId;
            return self::getProductInfo($productId);
        }
    }

    private static function getProductIdFromGrocyCode(string $barcode): ?int {
        if (!stringStartsWith($barcode, "GRCY:P:"))
            return null;
        // regular grocycode
        if (preg_match("/^GRCY:P:\d+$/", $barcode)) {
            return checkIfNumeric(str_replace("GRCY:P:", "", $barcode));
        }
        // grocycode related to specific stock entry
        if (preg_match("/^GRCY:P:\d+:.+$/", $barcode)) {
            $idArray = explode(":", $barcode);
            return checkIfNumeric($idArray[2]);
        }
        return null;
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
                    return GrocyBarcode::parseAnyBarcodes($cachedBarcodes);
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
        $result = GrocyBarcode::parseAnyBarcodes($curlResult);
        if ($updateRedis)
            RedisConnection::cacheAllBarcodes($curlResult);
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
     *
     * @param int $choreId
     *
     * @return void
     */
    public static function executeChore(int $choreId): void {

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
     *
     * @return void
     */
    public static function processError(Exception $e, string $errorMessage): void {
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
     *
     * @return void
     */
    public static function logError(string $errorMessage, bool $isFatal = true): void {
        try {
            DatabaseConnection::getInstance()->saveError($errorMessage, $isFatal);
        } catch (DbConnectionDuringEstablishException $_) {
            // Error occurred during the DB connection. As such, DB is not available to log the error.
        }
    }

    /**
     * Runs a benchmark for debugging purposes
     *
     * @param int $id Product ID of grocy product to benchmark
     *
     * @return never
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

    /**
     * @param int|string|true $param
     */
    private static function benchmarkApiCall(string $apiCall, ...$param): void {
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
