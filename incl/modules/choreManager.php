<?php

require_once __DIR__ . "/../processing.inc.php";

class ChoreManager {

    const GROCY_CODE_CHORE = "GRCY:C:";

    /**
     * Gets an array of locally stored chore barcodes
     * @return Chore[]
     * @throws DbConnectionDuringEstablishException
     */
    public static function getBarcodes(): array {
        $db     = DatabaseConnection::getInstance()->getDatabaseReference();
        $res    = $db->query('SELECT * FROM ChoreBarcodes');
        $chores = array();
        while ($row = $res->fetchArray()) {
            array_push($chores, new Chore($row));
        }
        return $chores;
    }


    /**
     * Updates a chore barcode
     *
     * @param int $choreId
     * @param string $choreBarcode
     *
     * @return void
     * @throws DbConnectionDuringEstablishException
     *
     */
    public static function updateBarcode(int $choreId, string $choreBarcode): void {
        checkIfNumeric($choreId);
        $db          = DatabaseConnection::getInstance()->getDatabaseReference();
        $choreBarcode = str_replace('&#39;', "", $choreBarcode);
        $stmt = $db->prepare("REPLACE INTO ChoreBarcodes(choreId, barcode) VALUES(:choreId, :barcode)");
        $stmt->bindValue(':choreId', $choreId, SQLITE3_INTEGER);
        $stmt->bindValue(':barcode', $choreBarcode, SQLITE3_TEXT);
        $stmt->execute();
    }


    /**
     * Deletes a barcode associated with a chore
     *
     * @param int $id
     *
     * @return void
     * @throws DbConnectionDuringEstablishException
     *
     */
    public static function deleteBarcode(int $id): void {
        checkIfNumeric($id);
        $db   = DatabaseConnection::getInstance()->getDatabaseReference();
        $stmt = $db->prepare("DELETE FROM ChoreBarcodes WHERE choreId=:id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
    }

    /**
     * Checks if barcode is associated with a chore
     * @param string $barcode
     * @return bool
     * @throws DbConnectionDuringEstablishException
     */
    public static function isChoreBarcode(string $barcode): bool {
        return (self::isGrocyCode($barcode) || self::getChoreBarcode($barcode) != null);
    }

    /**
     * Checks if a barcode is a GrocyCode
     * @param string $barcode
     * @return bool
     */
    public static function isGrocyCode(string $barcode): bool {
        return stringStartsWith($barcode, ChoreManager::GROCY_CODE_CHORE);
    }

    /**
     * Returns the ID from a grocyCode
     * @param string $barcode
     * @return string ID from barcode, NOT checked for validity!
     */
    public static function getIdFromGrocyCode(string $barcode): string {
        return str_replace(ChoreManager::GROCY_CODE_CHORE, "", $barcode);
    }


    /**
     * Get chore row from barcode
     * @param string $barcode Barcode to check
     * @return array|null DB row or null if not found
     * @throws DbConnectionDuringEstablishException
     */
    public static function getChoreBarcode(string $barcode): ?array {
        $db   = DatabaseConnection::getInstance()->getDatabaseReference();
        $stmt = $db->prepare("SELECT * FROM ChoreBarcodes WHERE barcode=:barcode");
        $stmt->bindValue(':barcode', $barcode, SQLITE3_TEXT);
        $res = $stmt->execute();
        if ($row = $res->fetchArray()) {
            return $row;
        } else {
            return null;
        }
    }
}


class Chore {

    public $id;
    public $choreId;
    public $barcode;


    public function __construct(array $dbRow) {
        if (!$this->isValidRow($dbRow)) {
            throw new RuntimeException("Invalid row supplied to create Chore Object");
        }
        $this->id      = $dbRow['id'];
        $this->choreId = $dbRow['choreId'];
        $this->barcode = $dbRow['barcode'];
    }


    private function isValidRow(array $dbRow): bool {
        return (array_key_exists('id', $dbRow) &&
            array_key_exists('choreId', $dbRow) &&
            array_key_exists('barcode', $dbRow));
    }
}