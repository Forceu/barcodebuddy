<?php

class QuantityManager {

    /**
     * Gets an array of locally stored quantities
     * @return ApiQuantity[]
     */
    public static function getQuantities(): array {
        $barcodes       = array();
        $storedBarcodes = API::getAllBarcodes();
        $products       = API::getAllProductsInfo();
        foreach ($storedBarcodes as $barcode) {
            if ($barcode instanceof GrocyProductBarcode) {
                array_push($barcodes, new ApiQuantity($barcode, $products[$barcode->productId]));
            }
        }
        return $barcodes;
    }


    /**
     * Gets quantity for stored barcode quantities or 1 if not found
     *
     * @param string $barcode
     * @param bool $deleteAfterCompletion Deletes the entry after before returning the amount
     * @param SQLite3|null $db A DB reference can be passed. Only use for upgrading DB to new version!
     * @return float quantity or 1 if not found
     * @throws DbConnectionDuringEstablishException
     */
    public static function getStoredQuantityForBarcode(string $barcode, bool $deleteAfterCompletion = false, SQLite3 $db = null): ?float {
        if ($db == null)
            $db = DatabaseConnection::getInstance()->getDatabaseReference();
        $res = $db->query("SELECT * FROM Quantities WHERE barcode='$barcode'");
        if ($row = $res->fetchArray()) {
            $entry = new Quantity($row);
            if ($deleteAfterCompletion)
                self::delete($entry->id, $db);
            return $entry->quantity;
        } else {
            return null;
        }
    }

    /**
     * If a stored amount was found, add this to Grocy
     *
     * @param SQLite3|null $db A DB reference can be passed. Only use for upgrading DB to new version!
     * @param string $barcode
     *
     * @return void
     * @throws DbConnectionDuringEstablishException
     *
     */
    public static function syncBarcodeToGrocy(string $barcode, SQLite3 $db = null): void {
        $storedAmount = self::getStoredQuantityForBarcode($barcode, true, $db);
        if ($storedAmount != null) {
            API::addBarcodeQuantity($barcode, $storedAmount);
            //Only store log if not currently upgrading db
            if ($db == null) {
                $log = new LogOutput("Set quantity to $storedAmount for barcode $barcode", EVENT_TYPE_ASSOCIATE_PRODUCT);
                $log->setVerbose()->dontSendWebsocket()->createLog();
            }
        }
    }

    /**
     * @param $barcode string
     * @param $isConsume bool
     * @param $productInfo GrocyProduct
     * @return float
     * @throws DbConnectionDuringEstablishException
     */
    public static function getQuantityForBarcode(string $barcode, bool $isConsume, GrocyProduct $productInfo): float {
        $config = BBConfig::getInstance();
        $quantity = ($isConsume && $config["CONSUME_SAVED_QUANTITY"]) ? QuantityManager::getStoredQuantityForBarcode($barcode) : null;
        $qu = !$config["USE_GROCY_QU_FACTOR"] ? $productInfo->unit : null;
        $amount = $productInfo->getAmountByBarcode($barcode, $isConsume, $qu, $quantity);
        if ($amount == null) {
            $log = new LogOutput("Failed to find amount for barcode $barcode of " . $productInfo->name, EVENT_TYPE_ERROR);
            $log->setVerbose()->dontSendWebsocket()->createLog();
            return $quantity ?? 1;
        }
        return $amount;
    }


    /**
     * Adds a default quantity for a barcode or updates the product
     *
     * @param string $barcode
     * @param float $amount
     * @param string|null $product
     *
     * @return void
     * @throws DbConnectionDuringEstablishException
     */
    public static function addUpdateEntry(string $barcode, float $amount, string $product = null): void {
        $db = DatabaseConnection::getInstance()->getDatabaseReference();
        if ($product == null) {
            $db->exec("REPLACE INTO Quantities(barcode, quantity) VALUES ('$barcode', $amount)");
        } else {
            $db->exec("REPLACE INTO Quantities(barcode, quantity, product) VALUES ('$barcode', $amount, '$product')");
        }
    }


    /**
     * Deletes Quantity barcode
     *
     * @param int $id
     * @param SQLite3|null $db A DB reference can be passed. Only use for upgrading DB to new version!
     *
     * @return void
     * @throws DbConnectionDuringEstablishException
     */
    public static function delete(int $id, SQLite3 $db = null): void {
        if ($db == null)
            $db = DatabaseConnection::getInstance()->getDatabaseReference();
        $db->exec("DELETE FROM Quantities WHERE id='$id'");
    }
}

class Quantity {

    public $id;
    public $barcode;
    public $quantity;
    public $product;


    public function __construct(array $dbRow) {
        if (!$this->isValidRow($dbRow)) {
            throw new RuntimeException("Invalid row supplied to create Quantity Object");
        }
        $this->id       = $dbRow['id'];
        $this->barcode  = $dbRow['barcode'];
        $this->quantity = $dbRow['quantity'];
        $this->product  = $dbRow['product'];
    }

    private function isValidRow(array $dbRow): bool {
        return (array_key_exists('id', $dbRow) &&
            array_key_exists('barcode', $dbRow) &&
            array_key_exists('quantity', $dbRow) &&
            array_key_exists('product', $dbRow));
    }
}


class ApiQuantity {

    public int $id;
    public string $barcode;
    public float $quantity;
    public string $product;

    /**
     * ApiQuantity constructor.
     * @param GrocyProductBarcode $barcodeInfo
     * @param GrocyProduct $productInfo
     */
    public function __construct(GrocyProductBarcode $barcodeInfo, GrocyProduct $productInfo) {
        $this->id       = $barcodeInfo->id;
        $this->barcode  = $barcodeInfo->barcode;
        $this->quantity = $barcodeInfo->amount;
        $this->product  = $productInfo->name;
    }

}
