<?php


class ChoreManager {
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
     * @throws DbConnectionDuringEstablishException
     */
    public static function updateBarcode(int $choreId, string $choreBarcode) {
        checkIfNumeric($choreId);
        $db = DatabaseConnection::getInstance()->getDatabaseReference();
        $db->exec("REPLACE INTO ChoreBarcodes(choreId, barcode) VALUES(" . $choreId . ", '" . str_replace('&#39;', "", $choreBarcode) . "')");
    }


    /**
     * Deletes a barcode associated with a chore
     * @param int $id
     * @throws DbConnectionDuringEstablishException
     */
    public static function deleteBarcode(int $id) {
        checkIfNumeric($id);
        $db = DatabaseConnection::getInstance()->getDatabaseReference();
        $db->exec("DELETE FROM ChoreBarcodes WHERE choreId='$id'");
    }

    /**
     * Checks if barcode is associated with a chore
     * @param string $barcode
     * @return bool
     * @throws DbConnectionDuringEstablishException
     */
    public static function isChoreBarcode(string $barcode): bool {
        return (self::getChoreBarcode($barcode) != null);
    }


    /**
     * Get chore row from barcode
     * @param string $barcode Barcode to check
     * @return array|null DB row or null if not found
     * @throws DbConnectionDuringEstablishException
     */
    public static function getChoreBarcode(string $barcode): ?array {
        $db  = DatabaseConnection::getInstance()->getDatabaseReference();
        $res = $db->query("SELECT * FROM ChoreBarcodes WHERE barcode='$barcode'");
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


    public function __construct($dbRow) {
        if (!$this->isValidRow($dbRow)) {
            throw new RuntimeException("Invalid row supplied to create Chore Object");
        }
        $this->id      = $dbRow['id'];
        $this->choreId = $dbRow['choreId'];
        $this->barcode = $dbRow['barcode'];
    }


    private function isValidRow($dbRow): bool {
        return (array_key_exists('id', $dbRow) &&
            array_key_exists('choreId', $dbRow) &&
            array_key_exists('barcode', $dbRow));
    }
}