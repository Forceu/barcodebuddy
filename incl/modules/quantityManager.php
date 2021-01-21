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
            if ($barcode["factor"] != null) {
                array_push($barcodes, new ApiQuantity($barcode, $products));
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
     * @return int quantity or 1 if not found
     * @throws DbConnectionDuringEstablishException
     */
    public static function getStoredQuantityForBarcode(string $barcode, bool $deleteAfterCompletion = false, SQLite3 $db = null): int {
        if ($db == null)
            $db = DatabaseConnection::getInstance()->getDatabaseReference();
        $res = $db->query("SELECT * FROM Quantities WHERE barcode='$barcode'");
        if ($row = $res->fetchArray()) {
            $entry = new Quantity($row);
            if ($deleteAfterCompletion)
                self::delete($entry->id, $db);
            return $entry->quantity;
        } else {
            return 1;
        }
    }

    /**
     * If a stored amount was found, add this to Grocy
     * @param SQLite3|null $db A DB reference can be passed. Only use for upgrading DB to new version!
     * @param string $barcode
     * @throws DbConnectionDuringEstablishException
     */
    public static function syncBarcodeToGrocy(string $barcode, SQLite3 $db = null) {
        $storedAmount = self::getStoredQuantityForBarcode($barcode, true, $db);
        if ($storedAmount != 1) {
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
     * @return int
     * @throws DbConnectionDuringEstablishException
     */
    public static function getQuantityForBarcode(string $barcode, bool $isConsume, GrocyProduct $productInfo): int {
        $config = BBConfig::getInstance();
        if ($isConsume && !$config["CONSUME_SAVED_QUANTITY"])
            return 1;
        $amountSavedInProduct = intval($productInfo->quFactor);
        $barcodes             = API::getAllBarcodes();
        if (isset($barcodes[$barcode]) && $barcodes[$barcode]["factor"] != null)
            return $barcodes[$barcode]["factor"];
        if ($config["USE_GROCY_QU_FACTOR"] && $amountSavedInProduct > 1)
            return $amountSavedInProduct;
        return $amount = QuantityManager::getStoredQuantityForBarcode($barcode);
    }


    /**
     * Adds a default quantity for a barcode or updates the product
     *
     * @param $barcode
     * @param $amount
     * @param $product
     * @throws DbConnectionDuringEstablishException
     */
    public static function addUpdateEntry($barcode, $amount, $product = null) {
        checkIfNumeric($amount);
        $db = DatabaseConnection::getInstance()->getDatabaseReference();
        if ($product == null) {
            $db->exec("REPLACE INTO Quantities(barcode, quantity) VALUES ('$barcode', $amount)");
        } else {
            $db->exec("REPLACE INTO Quantities(barcode, quantity, product) VALUES ('$barcode', $amount, '$product')");
        }
    }


    /**
     * Deletes Quantity barcode
     * @param $id
     * @param SQLite3|null $db A DB reference can be passed. Only use for upgrading DB to new version!
     * @throws DbConnectionDuringEstablishException
     */
    public static function delete($id, SQLite3 $db = null) {
        checkIfNumeric($id);
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


    public function __construct($dbRow) {
        if (!$this->isValidRow($dbRow)) {
            throw new RuntimeException("Invalid row supplied to create Quantity Object");
        }
        $this->id       = $dbRow['id'];
        $this->barcode  = $dbRow['barcode'];
        $this->quantity = $dbRow['quantity'];
        $this->product  = $dbRow['product'];
    }

    private function isValidRow($dbRow): bool {
        return (array_key_exists('id', $dbRow) &&
            array_key_exists('barcode', $dbRow) &&
            array_key_exists('quantity', $dbRow) &&
            array_key_exists('product', $dbRow));
    }
}


class ApiQuantity {

    public $id;
    public $barcode;
    public $quantity;
    public $product;

    /**
     * ApiQuantity constructor.
     * @param array $barcodeArrayItem
     * @param GrocyProduct[] $productList
     */
    public function __construct(array $barcodeArrayItem, array $productList) {
        $this->id       = $barcodeArrayItem['barcode_id'];
        $this->barcode  = $barcodeArrayItem['barcode'];
        $this->quantity = $barcodeArrayItem['factor'];
        $this->product  = $productList[$barcodeArrayItem['id']]->name;
    }

}
