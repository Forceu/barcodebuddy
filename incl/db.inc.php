<?php
/**
 * Barcode Buddy for Grocy
 *
 * PHP version 7
 *
 *
 * Helper file for database connection
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */


require_once __DIR__ . "/processing.inc.php";
require_once __DIR__ . "/PluginLoader.php";
require_once __DIR__ . "/api.inc.php";
require_once __DIR__ . "/websocketconnection.inc.php";
require_once __DIR__ . "/configProcessing.inc.php";
require_once __DIR__ . "/modules/tagManager.php";
require_once __DIR__ . "/modules/choreManager.php";
require_once __DIR__ . "/modules/quantityManager.php";
require_once __DIR__ . "/modules/barcodeFederation.php";
require_once __DIR__ . "/modules/dbUpgrade.php";


//States to tell the script what to do with the barcodes that were scanned
const STATE_CONSUME         = 0;
const STATE_CONSUME_SPOILED = 1;
const STATE_PURCHASE        = 2;
const STATE_OPEN            = 3;
const STATE_GETSTOCK        = 4;
const STATE_ADD_SL          = 5;
const STATE_CONSUME_ALL     = 6;

const SECTION_KNOWN_BARCODES   = "known";
const SECTION_UNKNOWN_BARCODES = "unknown";
const SECTION_A_REQ_BARCODES   = "req_actions";
const SECTION_LOGS             = "log";


const LOOKUP_ID_OPENFOODFACTS = "1";
const LOOKUP_ID_UPCDB         = "2";
const LOOKUP_ID_UPCDATABASE   = "3";
const LOOKUP_ID_ALBERTHEIJN   = "4";
const LOOKUP_ID_JUMBO         = "5";
const LOOKUP_ID_OPENGTINDB    = "6";
const LOOKUP_ID_FEDERATION    = "7";
const LOOKUP_ID_PLUS          = "8";

/**
 * Dockerfile changes this to "1", so that the default is true
 * For non-docker this should be false ("0").
 * If the Dockerfile was overriding the env, the user could not
 * disable Redis.
 */
const DEFAULT_USE_REDIS = "0";

/**
 * Thrown when a database connection is already being setup and a new connection is requested
 * This happens most likely when calling getInstance() during the database upgrade
 */
class DbConnectionDuringEstablishException extends Exception {

}

/**
 * Creates a database connection and offers DB functions
 */
class DatabaseConnection {

    /* 1 is used for true and 0 for false, as PHP interprets the String "false" as Boolean "true" */
    const DEFAULT_VALUES = array(
        "BARCODE_C"                     => "BBUDDY-C",
        "BARCODE_CS"                    => "BBUDDY-CS",
        "BARCODE_CA"                    => "BBUDDY-CA",
        "BARCODE_P"                     => "BBUDDY-P",
        "BARCODE_O"                     => "BBUDDY-O",
        "BARCODE_GS"                    => "BBUDDY-I",
        "BARCODE_Q"                     => "BBUDDY-Q-",
        "BARCODE_AS"                    => "BBUDDY-AS",
        "REVERT_TIME"                   => "10",
        "REVERT_SINGLE"                 => "1",
        "MORE_VERBOSE"                  => "1",
        "GROCY_API_URL"                 => null,
        "GROCY_API_KEY"                 => null,
        "LAST_BARCODE"                  => null,
        "LAST_PRODUCT"                  => null,
        "WS_FULLSCREEN"                 => "0",
        "SHOPPINGLIST_REMOVE"           => "1",
        "USE_GENERIC_NAME"              => "1",
        "CONSUME_SAVED_QUANTITY"        => "0",
        "USE_GROCY_QU_FACTOR"           => "0",
        "SHOW_STOCK_ON_SCAN"            => "0",
        "LOOKUP_USE_OFF"                => "1",
        "LOOKUP_USE_UPC"                => "1",
        "LOOKUP_USE_JUMBO"              => "0",
        "LOOKUP_USE_AH"                 => "0",
        "LOOKUP_USE_PLUS"               => "0",
        "LOOKUP_USE_UPC_DATABASE"       => "0",
        "LOOKUP_USE_OPEN_GTIN_DATABASE" => "0",
        "LOOKUP_USE_BBUDDY_SERVER"      => "0",
        "LOOKUP_UPC_DATABASE_KEY"       => null,
        "LOOKUP_OPENGTIN_KEY"           => null,
        "USE_REDIS"                     => DEFAULT_USE_REDIS,
        "REDIS_IP"                      => "127.0.0.1",
        "REDIS_PORT"                    => "6379",
        "REDIS_PW"                      => "",
        "BBUDDY_SERVER_UUID"            => null,
        "BBUDDY_SERVER_ENABLED"         => "0",
        "BBUDDY_SERVER_POPUPSHOWN"      => "0",
        "BBUDDY_SERVER_NEXTSYNC"        => "0",
        "LOOKUP_ORDER"                  => LOOKUP_ID_OPENFOODFACTS . "," .
            LOOKUP_ID_UPCDB . "," .
            LOOKUP_ID_UPCDATABASE . "," .
            LOOKUP_ID_ALBERTHEIJN . "," .
            LOOKUP_ID_PLUS . "," .
            LOOKUP_ID_JUMBO . "," .
            LOOKUP_ID_OPENGTINDB . "," .
            LOOKUP_ID_FEDERATION);


    const DB_INT_VALUES = array("REVERT_TIME");

    /**
     * @var SQLite3
     */
    private $db;
    private static $_ConnectionInstance = null;
    private static $_StartingConnection = false;

    private function __construct() {
        $this->initDb();
    }

    /**
     * Get an instance of DatabaseConnection
     * If an existing instance is available, it will be used.
     * If not available, and no instance is being created, a new connection will be established.
     * Otherwise (such as during an ongoing upgrade in this php instance) an error will be thrown
     *
     * @return DatabaseConnection
     *
     * @throws DbConnectionDuringEstablishException
     */
    static function getInstance(): DatabaseConnection {
        if (self::$_StartingConnection) {
            throw new DbConnectionDuringEstablishException();
        }

        if (self::$_ConnectionInstance != null) {
            return self::$_ConnectionInstance;
        }

        self::$_StartingConnection = true;
        self::$_ConnectionInstance = new DatabaseConnection();
        self::$_StartingConnection = false;
        return self::$_ConnectionInstance;
    }

    /**
     * Initiate database and create global variable for config
     *
     * @throws DbConnectionDuringEstablishException
     *
     * @return void
     */
    private function initDb(): void {
        global $CONFIG;

        self::checkPermissions();
        $this->db = new SQLite3($CONFIG->DATABASE_PATH);
        $this->db->busyTimeout(5000);
        $this->db->exec("CREATE TABLE IF NOT EXISTS Barcodes(id INTEGER PRIMARY KEY, barcode TEXT NOT NULL, name TEXT NOT NULL, possibleMatch INTEGER, amount INTEGER NOT NULL, requireWeight INTEGER, bestBeforeInDays INTEGER, price TEXT, bbServerAltNames TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS Tags(id INTEGER PRIMARY KEY, tag TEXT NOT NULL, itemId INTEGER NOT NULL)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS TransactionState(id INTEGER PRIMARY KEY, currentState TINYINT NOT NULL, since INTEGER NOT NULL)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS BarcodeLogs(id INTEGER PRIMARY KEY, log TEXT NOT NULL)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS BBConfig(id INTEGER PRIMARY KEY, data TEXT UNIQUE NOT NULL, value TEXT NOT NULL)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS ChoreBarcodes(id INTEGER PRIMARY KEY, choreId INTEGER UNIQUE, barcode TEXT NOT NULL )");
        $this->db->exec("CREATE TABLE IF NOT EXISTS Quantities(id INTEGER PRIMARY KEY, barcode TEXT NOT NULL UNIQUE, quantity INTEGER NOT NULL, product TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS ApiKeys(id INTEGER PRIMARY KEY, key TEXT NOT NULL UNIQUE, lastused INTEGER NOT NULL)");
        $this->insertDefaultValues();
        $previousVersion = intval(BBConfig::getInstance($this)["version"]);
        if ($previousVersion < BB_VERSION) {
            (new DbUpgrade($this))->upgradeBarcodeBuddy($previousVersion);
            BBConfig::forceRefresh();
        }
    }

    /**
     * Inserts default values for Barcode Buddy Config
     *
     * @return void
     */
    private function insertDefaultValues(): void {
        $this->db->exec("INSERT INTO TransactionState(id,currentState,since) SELECT 1, 0, datetime('now','localtime') WHERE NOT EXISTS(SELECT 1 FROM TransactionState WHERE id = 1)");
        $this->db->exec("INSERT INTO BBConfig(id,data,value) SELECT 1, \"version\", \"" . BB_VERSION . "\" WHERE NOT EXISTS(SELECT 1 FROM BBConfig WHERE id = 1)");
        foreach (self::DEFAULT_VALUES as $key => $value) {
            $this->db->exec("INSERT INTO BBConfig(data,value) SELECT \"" . $key . "\", \"" . $value . "\" WHERE NOT EXISTS(SELECT 1 FROM BBConfig WHERE data = '$key')");
        }
    }

    /**
     * Save last used barcode into DB
     *
     * @param $barcode
     * @param null $name
     *
     * @return void
     */
    public function saveLastBarcode(string $barcode, $name = null): void {
        $this->updateConfig("LAST_BARCODE", $barcode);
        $this->updateConfig("LAST_PRODUCT", $name);
    }

    /**
     * Checks if database is writable
     *
     * @return void
     */
    private function checkPermissions(): void {
        global $CONFIG;
        if (file_exists($CONFIG->DATABASE_PATH)) {
            if (!is_writable($CONFIG->DATABASE_PATH)) {
                showErrorNotWritable("DB Error: DB_Not_Writable");
            }
        } else {
            DbUpgrade::createDbDirectory();
            DbUpgrade::checkAndMoveIfOldDbLocation();
            if (!is_writable(dirname($CONFIG->DATABASE_PATH))) {
                showErrorNotWritable("DB Error Not_Writable");
            }
        }
    }


    /**
     * Getting the state
     * TODO: Change date in log
     * @return int
     */
    public function getTransactionState(): int {
        $res = $this->db->query("SELECT * FROM TransactionState");
        if ($row = $res->fetchArray()) {
            $state = $row["currentState"];
            $since = $row["since"];
            if ($state == STATE_CONSUME || $this->revertBackToConsume($since))
                return STATE_CONSUME;
            else
                return $state;
        } else {
            die("DB Error");
        }
    }

    /**
     * Checks if enough time has passed to revert to STATE_CONSUME
     * @param int $timestamp timestamp when state was set
     * @return bool True if REVERT_TIME = 0 or enough time has passed
     */
    private function revertBackToConsume(int $timestamp): bool {
        $revertTime = BBConfig::getInstance()["REVERT_TIME"];
        if ($revertTime == 0)
            return false;
        $differenceInMinutes = round(abs($this->getTimestamp() - $timestamp) / 60, 0);
        return ($differenceInMinutes > BBConfig::getInstance()["REVERT_TIME"]);
    }

    /**
     * Returns the current timestamp
     * @return int
     */
    private function getTimestamp(): int {
        $date = new DateTime();
        return $date->getTimestamp();
    }


    /**
     * Setting the state
     *
     * @param $state
     *
     * @return void
     */
    public function setTransactionState(int $state): void {
        /** @noinspection SqlWithoutWhere */
        $this->db->exec("UPDATE TransactionState SET currentState=$state, since=" . $this->getTimestamp());
        SocketConnection::sendWebsocketStateChange($state);
    }

    /**
     * Gets an array of locally stored barcodes
     * @return array
     */
    public function getStoredBarcodes(): array {
        $res                 = $this->db->query('SELECT * FROM Barcodes');
        $barcodes            = array();
        $barcodes["known"]   = array();
        $barcodes["unknown"] = array();
        $barcodes["tare"]    = array();
        while ($row = $res->fetchArray()) {
            $item                     = array();
            $item['id']               = $row['id'];
            $item['barcode']          = $row['barcode'];
            $item['amount']           = $row['amount'];
            $item['name']             = $row['name'];
            $item['match']            = $row['possibleMatch'];
            $item['tare']             = $row['requireWeight'];
            $item['bestBeforeInDays'] = $row['bestBeforeInDays'];
            $item['price']            = $row['price'];
            $item['bbServerAltNames'] = json_decode($row['bbServerAltNames']);

            if ($item['tare'] == "1") {
                array_push($barcodes["tare"], $item);
            } elseif ($row['name'] != "N/A") {
                array_push($barcodes["known"], $item);
            } else {
                array_push($barcodes["unknown"], $item);
            }
        }
        return $barcodes;
    }

    /**
     * Returns stored amount of saved barcodes that is not associated with a product yet
     * Not to be confused with default amount for barcodes
     * @param $barcode
     * @return int
     */
    public function getStoredBarcodeAmount(string $barcode): int {
        $res = $this->db->query("SELECT * FROM Barcodes WHERE barcode='$barcode'");
        if ($row = $res->fetchArray()) {
            return $row['amount'];
        } else {
            return 0;
        }
    }

    /**
     * gets barcode stored in DB by ID
     * @param $id
     * @return array|false
     */
    public function getBarcodeById(string $id) {
        $res = $this->db->query("SELECT * FROM Barcodes WHERE id='$id'");
        return $res->fetchArray();
    }


    /**
     * Sets the possible match for a barcode that has a tag in its name
     *
     * @param $barcode
     * @param $productId
     *
     * @return void
     */
    public function updateSavedBarcodeMatch(string $barcode, $productId): void {
        checkIfNumeric($productId);
        $this->db->exec("UPDATE Barcodes SET possibleMatch='$productId' WHERE barcode='$barcode'");
    }


    /**
     * Returns true if an unknown barcode is already in the list
     * @param $barcode
     * @return bool
     */
    public function isUnknownBarcodeAlreadyStored(string $barcode): bool {
        $count = $this->db->querySingle("SELECT COUNT(*) as count FROM Barcodes WHERE barcode='$barcode'");
        return ($count != 0);
    }

    /**
     * Increases quantity of a saved barcode (not to confuse with default quantity)
     *
     * @param $barcode
     * @param $amount
     *
     * @return void
     */
    public function addQuantityToUnknownBarcode(string $barcode, int $amount): void {
        $this->db->exec("UPDATE Barcodes SET amount = amount + $amount WHERE barcode = '$barcode'");

    }

    /**
     * Sets quantity of a saved barcode (not to confuse with default quantity)
     *
     * @param $barcode
     * @param $amount
     *
     * @return void
     */
    public function setQuantityToUnknownBarcode(string $barcode, int $amount): void {
        $this->db->exec("UPDATE Barcodes SET amount = $amount WHERE barcode = '$barcode'");
    }

    /**
     * Add an unknown barcode
     *
     * @param string $barcode
     * @param int $amount
     * @param string|null $bestBeforeInDays
     * @param string|null $price
     * @param array|null $productname
     * @param int $match
     *
     * @return void
     */
    public function insertUnrecognizedBarcode(string $barcode, int $amount = 1, string $bestBeforeInDays = null, string $price = null, ?array $productname = null): void {
        if ($bestBeforeInDays == null)
            $bestBeforeInDays = "NULL";

        if ($productname == null) {
            $name     = "N/A";
            $altNames = "NULL";
            $match    = 0;
        } else {
            $name     = $productname["name"];
            $match    = TagManager::getProductIdByPossibleTag($name, $this->db);
            $altNames = "'" . $productname["altNames"] . "'";
        }

        $this->db->exec("INSERT INTO Barcodes(barcode, name, amount, possibleMatch, requireWeight, bestBeforeInDays, price, bbServerAltNames)
                             VALUES('$barcode', '$name', $amount, $match, 0, $bestBeforeInDays, '$price', $altNames)");
    }

    /**
     * @param null|string $bestBeforeInDays
     * @param null|string $price
     */
    public function insertActionRequiredBarcode(string $barcode, ?string $bestBeforeInDays = null, ?string $price = null): void {
        if ($bestBeforeInDays == null)
            $bestBeforeInDays = "NULL";

        $this->db->exec("INSERT INTO Barcodes(barcode, name, amount, possibleMatch, requireWeight, bestBeforeInDays, price)
                             VALUES('$barcode', 'N/A', 1, 0, 1, $bestBeforeInDays, '$price')");
    }

    public function updateUnrecognizedBarcodeName(string $barcode, string $name): void {
        $match = TagManager::getProductIdByPossibleTag($name, $this->db);
        $this->db->exec("UPDATE Barcodes SET name='$name', possibleMatch=$match WHERE barcode='$barcode'");
    }

    /**
     * Gets an array of BBuddy API keys
     * @return array
     */
    public function getStoredApiKeys(): array {
        $res     = $this->db->query('SELECT * FROM ApiKeys');
        $apikeys = array();
        while ($row = $res->fetchArray()) {
            $item             = array();
            $item['id']       = $row['id'];
            $item['key']      = $row['key'];
            $item['lastused'] = $row['lastused'];
            array_push($apikeys, $item);
        }
        return $apikeys;
    }

    public function isValidApiKey($apiKey): bool {
        foreach ($this->getStoredApiKeys() as $key) {
            if ($apiKey === $key["key"]) {
                $this->db->exec("UPDATE ApiKeys SET lastused=datetime('now','localtime') WHERE id=" . $key["id"]);
                return true;
            }
        }
        return false;
    }


    /**
     * Generates API key
     * @return false|string
     */
    public function generateApiKey() {
        $key = generateRandomString();
        $this->db->exec("INSERT INTO ApiKeys(key, lastused) VALUES('" . $key . "', 'Never');");
        return $key;
    }


    /**
     * Deletes API key
     *
     * @param $id
     *
     * @return void
     */
    public function deleteApiKey($id): void {
        checkIfNumeric($id);
        $this->db->exec("DELETE FROM ApiKeys WHERE id='$id'");
    }


    /**
     * Deletes all API keys
     *
     * @return void
     */
    public function deleteAllApiKeys(): void {
        /** @noinspection SqlWithoutWhere */
        $this->db->exec("DELETE FROM ApiKeys");
    }


    /**
     * Get all stored logs
     * @return array
     */
    public function getLogs(): array {
        $res  = $this->db->query('SELECT * FROM BarcodeLogs ORDER BY id DESC');
        $logs = array();
        while ($row = $res->fetchArray()) {
            array_push($logs, $row['log']);
        }
        return $logs;
    }


    public function saveError(string $errorMessage, bool $isFatal = true): void {
        $verboseError = '<span style="color: red;">' . sanitizeString($errorMessage) . '</span> Please check your URL and API key in the settings menu!';
        $this->saveLog($verboseError, false, true);
        if ($isFatal) {
            echo("<b>" . $verboseError . "</b>");
        }
    }

    /**
     * Save a log
     *
     * @param $log
     * @param bool $isVerbose
     * @param bool $isError
     * @param bool $isDebug
     *
     * @return void
     */
    public function saveLog(string $log, bool $isVerbose = false, bool $isError = false, bool $isDebug = false): void {
        if ($isVerbose == false || BBConfig::getInstance()["MORE_VERBOSE"] == true) {
            $date = date('Y-m-d H:i:s');
            if ($isError || $isDebug) {
                $logEntry = $date . ': ' . $log;
            } else {
                $logEntry = $date . ": " . sanitizeString($log);
            }
            $this->db->exec("INSERT INTO BarcodeLogs(log) VALUES('" . $logEntry . "')");
        }
    }


    /**
     * Delete barcode from local db
     *
     * @param $id
     *
     * @return void
     */
    public function deleteBarcode(string $id): void {
        $this->db->exec("DELETE FROM Barcodes WHERE id='$id'");
    }


    /**
     * Delete all saved barcodes
     *
     * @param $section
     *
     * @return void
     */
    public function deleteAll($section): void {
        switch ($section) {
            case SECTION_KNOWN_BARCODES:
                $this->db->exec("DELETE FROM Barcodes WHERE name IS NOT 'N/A'");
                break;
            case SECTION_UNKNOWN_BARCODES:
                $this->db->exec("DELETE FROM Barcodes WHERE name='N/A'");
                break;
            case SECTION_A_REQ_BARCODES:
                $this->db->exec("DELETE FROM Barcodes WHERE requireWeight='1'");
                break;
            case SECTION_LOGS:
                /** @noinspection SqlWithoutWhere */
                $this->db->exec("DELETE FROM BarcodeLogs");
                break;
        }
    }


    /**
     * @return mixed
     */
    function getRawConfig() {
        return $this->db->query("SELECT * FROM BBConfig");
    }

    //Sets the config key with new value
    /**
     * @param int|null|string $value
     */
    public function updateConfig(string $key, $value): void {
        if (in_array($key, self::DB_INT_VALUES)) {
            checkIfNumeric($value);
        }
        $this->db->exec("UPDATE BBConfig SET value='" . $value . "' WHERE data='$key'");
        BBConfig::getInstance()[$key] = $value;
    }

    public function getDatabaseReference(): SQLite3 {
        return $this->db;
    }
}
