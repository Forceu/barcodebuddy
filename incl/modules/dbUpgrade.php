<?php

/**
 * Barcode Buddy for Grocy
 *
 * PHP version 8
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 * @author     Marc Ole Bulling
 * @copyright  2021 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.8
 */

require_once __DIR__ . "/../db.inc.php";

class DbUpgrade {

    const LEGACY_DATABASE_PATH = __DIR__ . '/../barcodebuddy.db';

    private $db;
    private $databaseConnection;

    public function __construct(DatabaseConnection $databaseConnection) {
        $this->db                 = $databaseConnection->getDatabaseReference();
        $this->databaseConnection = $databaseConnection;
    }

    /**
     * Since BB 1.3.2 the database is in the /data/ folder.
     * If there is an old database, create the new folder and move it there.
     *
     * @return void
     */
    public static function checkAndMoveIfOldDbLocation(): void {
        global $CONFIG;
        //If only old db exists, create directory and move file
        if (file_exists(self::LEGACY_DATABASE_PATH) && !file_exists($CONFIG->DATABASE_PATH)) {
            self::createDbDirectory();
            $couldMove = rename(self::LEGACY_DATABASE_PATH, $CONFIG->DATABASE_PATH);
            if (!$couldMove) {
                showErrorNotWritable("DB Error Could_Not_Move");
            }
        }
    }


    public static function createDbDirectory(): void {
        global $CONFIG;
        $dirName = dirname($CONFIG->DATABASE_PATH);
        if (!file_exists($dirName)) {
            $couldCreateDir = mkdir($dirName, 0700, true);
            if (!$couldCreateDir) {
                showErrorNotWritable("DB Error Could_Not_Create_Dir");
            }
        }
    }

    /**
     * Is called after updating Barcode Buddy to a new version
     *
     * @param int $previousVersion Previously installed version
     *
     * @throws DbConnectionDuringEstablishException
     *
     * @return void
     */
    public function upgradeBarcodeBuddy(int $previousVersion): void {
        //We update version before the actual update routine, as otherwise the user cannot
        //reenter setup. As the login gets invalidated in such a case, the Grocy version
        //will be checked upon reentering.
        $this->db->exec("UPDATE BBConfig SET value='" . BB_VERSION . "' WHERE data='version'");
        //Place for future update protocols
        if ($previousVersion < 1211) {
            $config = BBConfig::getInstance();
            $this->databaseConnection->updateConfig("BARCODE_C", strtoupper($config["BARCODE_C"]));
            $this->databaseConnection->updateConfig("BARCODE_O", strtoupper($config["BARCODE_O"]));
            $this->databaseConnection->updateConfig("BARCODE_P", strtoupper($config["BARCODE_P"]));
            $this->databaseConnection->updateConfig("BARCODE_CS", strtoupper($config["BARCODE_CS"]));
        }
        if ($previousVersion < 1303) {
            $this->isSupportedGrocyVersionOrDie();
        }
        if ($previousVersion < 1501) {
            $this->db->exec("ALTER TABLE Barcodes ADD COLUMN requireWeight INTEGER");
        }
        if ($previousVersion < 1504) {
            $this->db->exec("ALTER TABLE Barcodes ADD COLUMN bestBeforeInDays INTEGER");
            $this->db->exec("ALTER TABLE Barcodes ADD COLUMN price TEXT");
            $this->isSupportedGrocyVersionOrDie();
        }
        if ($previousVersion < 1511) {
            //Only sqlite 3.25+ supports renaming columns, therefore creating new table instead
            $this->db->exec("ALTER TABLE Quantities RENAME TO Quantities_temp;");
            $this->db->exec("CREATE TABLE Quantities(id INTEGER PRIMARY KEY, barcode TEXT NOT NULL UNIQUE, quantity INTEGER NOT NULL, product TEXT)");
            $this->db->exec("INSERT INTO Quantities(id, barcode, quantity, product) SELECT id, barcode, quantitiy, product FROM Quantities_temp;");
            $this->db->exec("DROP TABLE Quantities_temp;");
        }
        if ($previousVersion < 1653) {
            $config = BBConfig::getInstance();
            if ($config["LOOKUP_ORDER"] != DatabaseConnection::DEFAULT_VALUES["LOOKUP_ORDER"]) {
                $this->databaseConnection->updateConfig("LOOKUP_ORDER", $config["LOOKUP_ORDER"] . ",6");
            }
        }
        if ($previousVersion < 1660) {
            $quantities = $this->getQuantitiesForUpgrade();
            foreach ($quantities as $quantity) {
                if ($quantity->product != null) {
                    try {
                        QuantityManager::syncBarcodeToGrocy($quantity->barcode, $this->db);
                    } catch (DbConnectionDuringEstablishException $e) {
                        $this->databaseConnection->saveError("Unable to sync quantity to Grocy: Barcode " . $quantity->barcode . ", Amount " . $quantity->quantity);
                    }
                } else {
                    $this->databaseConnection->saveError("Unable to sync quantity to Grocy, as barcode does not exist in Grocy: Barcode " . $quantity->barcode . ", Amount " . $quantity->quantity, true);
                    QuantityManager::delete($quantity->id, $this->db);
                }
            }
        }
        if ($previousVersion < 1800) {
            $config = BBConfig::getInstance();
            if ($config["LOOKUP_ORDER"] != DatabaseConnection::DEFAULT_VALUES["LOOKUP_ORDER"]) {
                $this->databaseConnection->updateConfig("LOOKUP_ORDER", $config["LOOKUP_ORDER"] . ",7");
            }
            $this->db->exec("ALTER TABLE Barcodes ADD COLUMN bbServerAltNames TEXT");
            $this->isSupportedGrocyVersionOrDie();
        }
        if ($previousVersion < 1802) {
            //In v1800 the db was not initialised properly for new installations. This has to be done again
            $columnInfo = $this->db->querySingle("SELECT COUNT(*) AS CNTREC FROM pragma_table_info('Barcodes') WHERE name='bbServerAltNames'");
            if ($columnInfo == 0)
                $this->db->exec("ALTER TABLE Barcodes ADD COLUMN bbServerAltNames TEXT");
        }
        if ($previousVersion < 1803) {
            $config = BBConfig::getInstance();
            if ($config["LOOKUP_ORDER"] != DatabaseConnection::DEFAULT_VALUES["LOOKUP_ORDER"]) {
                $this->databaseConnection->updateConfig("LOOKUP_ORDER", $config["LOOKUP_ORDER"] . ",8");
            }
        }
        if ($previousVersion < 1804) {
            $this->databaseConnection->setTransactionState(0);
        }
        if ($previousVersion < 1818) {
            $config = BBConfig::getInstance();
            if ($config["LOOKUP_ORDER"] != DatabaseConnection::DEFAULT_VALUES["LOOKUP_ORDER"]) {
                $this->databaseConnection->updateConfig("LOOKUP_ORDER", $config["LOOKUP_ORDER"] . ",9");
            }
        }
        RedisConnection::updateCache();
    }


    private function isSupportedGrocyVersionOrDie(): void {
        global $ERROR_MESSAGE;
        $ERROR_MESSAGE = null;
        $version       = API::getGrocyVersion();
        if ($version == null) {
            $ERROR_MESSAGE = "Unable to communicate with Grocy and get Grocy version.";
        } elseif (!API::isSupportedGrocyVersion($version)) {
            $ERROR_MESSAGE = "Grocy " . MIN_GROCY_VERSION . " or newer required. You are running $version, please upgrade your Grocy instance.";
        }
        if ($ERROR_MESSAGE != null) {
            $ERROR_MESSAGE .= " Click <a href=\"./setup.php\">here</a> to re-enter your credentials.";
            $this->databaseConnection->updateConfig("GROCY_API_KEY", null);
            include __DIR__ . "/../../error.php";
            die();
        }
    }


    /**
     * Gets the legacy Quantities stored, needed for the upgrade to 1.6.6.0
     * @return array
     */
    private function getQuantitiesForUpgrade(): array {
        $res      = $this->db->query('SELECT * FROM Quantities');
        $barcodes = array();
        while ($row = $res->fetchArray()) {
            array_push($barcodes, new Quantity($row));
        }
        return $barcodes;
    }

}
