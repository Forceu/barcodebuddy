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

const LEGACY_DATABASE_PATH = __DIR__ . '/../barcodebuddy.db';

/**
 * Thrown when a database connection is already being setup and a new connection is requested
 */
class DbConnectionDuringEstablishException extends Exception {

}

/**
 * Creates a database connection and offers DB functions
 */
class DatabaseConnection {

    /* 1 is used for true and 0 for false, as PHP interprets the String "false" as Boolean "true" */
    const DEFAULT_VALUES = array(
        "BARCODE_C"              => "BBUDDY-C",
        "BARCODE_CS"             => "BBUDDY-CS",
        "BARCODE_CA"             => "BBUDDY-CA",
        "BARCODE_P"              => "BBUDDY-P",
        "BARCODE_O"              => "BBUDDY-O",
        "BARCODE_GS"             => "BBUDDY-I",
        "BARCODE_Q"              => "BBUDDY-Q-",
        "BARCODE_AS"             => "BBUDDY-AS",
        "REVERT_TIME"            => "10",
        "REVERT_SINGLE"          => "1",
        "MORE_VERBOSE"           => "1",
        "GROCY_API_URL"          => null,
        "GROCY_API_KEY"          => null,
        "LAST_BARCODE"           => null,
        "LAST_PRODUCT"           => null,
        "WS_FULLSCREEN"          => "0",
        "SHOPPINGLIST_REMOVE"    => "1",
        "USE_GENERIC_NAME"       => "1",
        "CONSUME_SAVED_QUANTITY" => "0",
        "USE_GROCY_QU_FACTOR"    => "0",
        "SHOW_STOCK_ON_SCAN"     => "0",
        "LOOKUP_USE_OFF"         => "1",
        "LOOKUP_USE_UPC"         => "1",
        "LOOKUP_USE_JUMBO"       => "0",
        "LOOKUP_USE_UPC_DATABASE"=> "0",
        "LOOKUP_UPC_DATABASE_KEY"=> null);


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
    static function getInstance() {
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

    //Initiate database and create global variable for config
    private function initDb() {
        global $CONFIG;

        self::checkPermissions();
        $this->db = new SQLite3($CONFIG->DATABASE_PATH);
        $this->db->busyTimeout(5000);
        $this->db->exec("CREATE TABLE IF NOT EXISTS Barcodes(id INTEGER PRIMARY KEY, barcode TEXT NOT NULL, name TEXT NOT NULL, possibleMatch INTEGER, amount INTEGER NOT NULL, requireWeight INTEGER, bestBeforeInDays INTEGER, price TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS Tags(id INTEGER PRIMARY KEY, tag TEXT NOT NULL, itemId INTEGER NOT NULL)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS TransactionState(id INTEGER PRIMARY KEY, currentState TINYINT NOT NULL, since INTEGER NOT NULL)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS BarcodeLogs(id INTEGER PRIMARY KEY, log TEXT NOT NULL)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS BBConfig(id INTEGER PRIMARY KEY, data TEXT UNIQUE NOT NULL, value TEXT NOT NULL)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS ChoreBarcodes(id INTEGER PRIMARY KEY, choreId INTEGER UNIQUE, barcode TEXT NOT NULL )");
        $this->db->exec("CREATE TABLE IF NOT EXISTS Quantities(id INTEGER PRIMARY KEY, barcode TEXT NOT NULL UNIQUE, quantitiy INTEGER NOT NULL, product TEXT)");
        $this->db->exec("CREATE TABLE IF NOT EXISTS ApiKeys(id INTEGER PRIMARY KEY, key TEXT NOT NULL UNIQUE, lastused INTEGER NOT NULL)");
        $this->insertDefaultValues();
        $previousVersion = BBConfig::getInstance($this)["version"];
        if ($previousVersion < BB_VERSION) {
            $this->upgradeBarcodeBuddy($previousVersion);
            BBConfig::forceRefresh();
        }
    }

    //Inserts default values for Barcode Buddy Config
    private function insertDefaultValues() {
        $this->db->exec("INSERT INTO TransactionState(id,currentState,since) SELECT 1, 0, datetime('now','localtime') WHERE NOT EXISTS(SELECT 1 FROM TransactionState WHERE id = 1)");
        $this->db->exec("INSERT INTO BBConfig(id,data,value) SELECT 1, \"version\", \"" . BB_VERSION . "\" WHERE NOT EXISTS(SELECT 1 FROM BBConfig WHERE id = 1)");
        foreach (self::DEFAULT_VALUES as $key => $value) {
            $this->db->exec("INSERT INTO BBConfig(data,value) SELECT \"" . $key . "\", \"" . $value . "\" WHERE NOT EXISTS(SELECT 1 FROM BBConfig WHERE data = '$key')");
        }
    }

    //Save last used barcode into DB
    public function saveLastBarcode($barcode, $name = null) {
        $this->updateConfig("LAST_BARCODE", $barcode);
        $this->updateConfig("LAST_PRODUCT", $name);
    }

    //Checks if database is writable
    private function checkPermissions() {
        global $CONFIG;
        if (file_exists($CONFIG->DATABASE_PATH)) {
            if (!is_writable($CONFIG->DATABASE_PATH)) {
                showErrorNotWritable("DB Error: DB_Not_Writable");
            }
        } else {
            self::createDbDirectory();
            self::checkAndMoveIfOldDbLocation();
            if (!is_writable(dirname($CONFIG->DATABASE_PATH))) {
                showErrorNotWritable("DB Error Not_Writable");
            }
        }
    }

    private function createDbDirectory() {
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
     * Since BB 1.3.2 the database is in the /data/ folder.
     * If there is an old database, create the new folder and move it there.
     */
    private function checkAndMoveIfOldDbLocation() {
        global $CONFIG;
        //If only old db exists, create directory and move file
        if (file_exists(LEGACY_DATABASE_PATH) && !file_exists($CONFIG->DATABASE_PATH)) {
            self::createDbDirectory();
            $couldMove = rename(LEGACY_DATABASE_PATH, $CONFIG->DATABASE_PATH);
            if (!$couldMove) {
                showErrorNotWritable("DB Error Could_Not_Move");
            }
        }
    }

    //Is called after updating Barcode Buddy to a new version
    private function upgradeBarcodeBuddy($previousVersion) {
        //We update version before the actual update routine, as otherwise the user cannot
        //reenter setup. As the login gets invalidated in such a case, the Grocy version
        //will be checked upon reentering.
        $this->db->exec("UPDATE BBConfig SET value='" . BB_VERSION . "' WHERE data='version'");
        //Place for future update protocols
        if ($previousVersion < 1211) {
            $config = BBConfig::getInstance();
            $this->updateConfig("BARCODE_C", strtoupper($config["BARCODE_C"]));
            $this->updateConfig("BARCODE_O", strtoupper($config["BARCODE_O"]));
            $this->updateConfig("BARCODE_P", strtoupper($config["BARCODE_P"]));
            $this->updateConfig("BARCODE_CS", strtoupper($config["BARCODE_CS"]));
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
    }


    private function isSupportedGrocyVersionOrDie() {
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
            $this->updateConfig("GROCY_API_KEY", null);
            include __DIR__ . "/../error.php";
            die();
        }
    }


    //Getting the state TODO change date
    public function getTransactionState() {
        $res = $this->db->query("SELECT * FROM TransactionState");
        if ($row = $res->fetchArray()) {
            $state = $row["currentState"];
            $since = $row["since"];
            if ($state == STATE_CONSUME) {
                return STATE_CONSUME;
            } else {
                $stateSet            = strtotime($since);
                $now                 = strtotime($this->getDbTimeInLC());
                $differenceInMinutes = round(abs($now - $stateSet) / 60, 0);
                if ($differenceInMinutes > BBConfig::getInstance()["REVERT_TIME"]) {
                    $this->setTransactionState(STATE_CONSUME);
                    return STATE_CONSUME;
                } else {
                    return $state;
                }
            }
        } else {
            die("DB Error");
        }
    }

    //Gets the local tine wuth the DB function, more reliable than PHP
    private function getDbTimeInLC() {
        return $this->db->querySingle("SELECT datetime('now','localtime');");
    }

    //Setting the state
    public function setTransactionState($state) {
        /** @noinspection SqlWithoutWhere */
        $this->db->exec("UPDATE TransactionState SET currentState=$state, since=datetime('now','localtime')");
        sendWebsocketStateChange($state);
    }

    //Gets an array of locally stored barcodes
    public function getStoredBarcodes() {
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

    //Returns stored amount of saved barcodes that is not associated with a product yet
    //Not to be confused with default amount for barcodes
    public function getStoredBarcodeAmount($barcode) {
        $res = $this->db->query("SELECT * FROM Barcodes WHERE barcode='$barcode'");
        if ($row = $res->fetchArray()) {
            return $row['amount'];
        } else {
            return 0;
        }
    }

    //gets barcode stored in DB by ID
    public function getBarcodeById($id) {
        $res = $this->db->query("SELECT * FROM Barcodes WHERE id='$id'");
        return $res->fetchArray();
    }


    //Gets an array of locally stored quantities
    public function getQuantities() {
        $res      = $this->db->query('SELECT * FROM Quantities');
        $barcodes = array();
        while ($row = $res->fetchArray()) {
            $item             = array();
            $item['id']       = $row['id'];
            $item['barcode']  = $row['barcode'];
            $item['quantity'] = $row['quantitiy'];
            $item['product']  = $row['product'];
            array_push($barcodes, $item);
        }
        return $barcodes;
    }


    //Gets quantity for stored barcode quantities
    public function getQuantityByBarcode($barcode) {
        $res = $this->db->query("SELECT * FROM Quantities WHERE barcode='$barcode'");
        if ($row = $res->fetchArray()) {
            return $row['quantitiy'];
        } else {
            return 1;
        }
    }


    //Save product name if already stored as Quantity
    public function refreshQuantityProductName($barcode, $productname) {
        $res = $this->db->query("SELECT * FROM Quantities WHERE barcode='$barcode'");
        if ($row = $res->fetchArray()) {
            $this->db->exec("UPDATE Quantities SET product='$productname' WHERE barcode='$barcode'");
        }
    }


    //Gets an array of locally stored tags
    public function getStoredTags() {
        $res  = $this->db->query('SELECT * FROM Tags');
        $tags = array();
        while ($row = $res->fetchArray()) {
            $item           = array();
            $item['id']     = $row['id'];
            $item['name']   = $row['tag'];
            $item['itemId'] = $row['itemId'];
            $item['item']   = "";
            array_push($tags, $item);
        }
        return $tags;
    }

    //Adds tag to DB
    public function addTag($tag, $itemid) {
        $this->db->exec("INSERT INTO Tags(tag, itemId) VALUES('$tag', $itemid);");
    }

    //Returns true if $name is not saved as a tag yet
    public function tagNotUsedYet($name) {
        $count = $this->db->querySingle("SELECT COUNT(*) as count FROM Tags WHERE tag='" . $name . "'");
        return ($count == 0);
    }

    //Sets the possible match for a barcode that has a tag in its name
    public function updateSavedBarcodeMatch($barcode, $productId) {
        checkIfNumeric($productId);
        $this->db->exec("UPDATE Barcodes SET possibleMatch='$productId' WHERE barcode='$barcode'");
    }


    //Gets an array of locally stored chore barcodes
    public function getStoredChoreBarcodes() {
        $res    = $this->db->query('SELECT * FROM ChoreBarcodes');
        $chores = array();
        while ($row = $res->fetchArray()) {
            $item            = array();
            $item['id']      = $row['id'];
            $item['choreId'] = $row['choreId'];
            $item['barcode'] = $row['barcode'];
            array_push($chores, $item);
        }
        return $chores;
    }

    //Updates a chore barcode
    public function updateChoreBarcode($choreId, $choreBarcode) {
        checkIfNumeric($choreId);
        $this->db->exec("REPLACE INTO ChoreBarcodes(choreId, barcode) VALUES(" . $choreId . ", '" . str_replace('&#39;', "", $choreBarcode) . "')");
    }

    //Adds a default quantitiy for a barcodem or updates the product
    public function addUpdateQuantitiy($barcode, $amount, $product = null) {
        checkIfNumeric($amount);
        if ($product == null) {
            $this->db->exec("REPLACE INTO Quantities(barcode, quantitiy) VALUES ('$barcode', $amount)");
        } else {
            $this->db->exec("REPLACE INTO Quantities(barcode, quantitiy, product) VALUES ('$barcode', $amount, '$product')");
        }
    }

    //Deletes a barcode associated with a chore
    public function deleteChoreBarcode($id) {
        checkIfNumeric($id);
        $this->db->exec("DELETE FROM ChoreBarcodes WHERE choreId='$id'");
    }


    //Deletes Quantity barcode
    public function deleteQuantitiy($id) {
        checkIfNumeric($id);
        $this->db->exec("DELETE FROM Quantities WHERE id='$id'");
    }

    //Checks if barcode is associated with a chore
    public function isChoreBarcode($barcode) {
        return ($this->getChoreBarcode($barcode) != null);
    }

    //Get chore from barcode
    public function getChoreBarcode($barcode) {
        $res = $this->db->query("SELECT * FROM ChoreBarcodes WHERE barcode='$barcode'");
        if ($row = $res->fetchArray()) {
            return $row;
        } else {
            return null;
        }
    }

    //Returns true if an unknown barcode is already in the list
    public function isUnknownBarcodeAlreadyStored($barcode) {
        $count = $this->db->querySingle("SELECT COUNT(*) as count FROM Barcodes WHERE barcode='$barcode'");
        return ($count != 0);
    }

    //Increases quantitiy of a saved barcode (not to confuse with default quantitiy)
    public function addQuantitiyToUnknownBarcode($barcode, $amount) {
        $this->db->exec("UPDATE Barcodes SET amount = amount + $amount WHERE barcode = '$barcode'");

    }

    //Sets quantitiy of a saved barcode (not to confuse with default quantitiy)
    public function setQuantitiyToUnknownBarcode($barcode, $amount) {
        $this->db->exec("UPDATE Barcodes SET amount = $amount WHERE barcode = '$barcode'");
    }

    //Add an unknown barcode
    public function insertUnrecognizedBarcode($barcode, $amount = 1, $bestBeforeInDays = null, $price = null, $productname = "N/A", $match = 0) {
        if ($bestBeforeInDays == null)
            $bestBeforeInDays = "NULL";

        $this->db->exec("INSERT INTO Barcodes(barcode, name, amount, possibleMatch, requireWeight, bestBeforeInDays, price)
                             VALUES('$barcode', '$productname', $amount, $match, 0, $bestBeforeInDays, '$price')");
    }

    public function insertActionRequiredBarcode($barcode, $bestBeforeInDays = null, $price = null) {
        if ($bestBeforeInDays == null)
            $bestBeforeInDays = "NULL";

        $this->db->exec("INSERT INTO Barcodes(barcode, name, amount, possibleMatch, requireWeight, bestBeforeInDays, price)
                             VALUES('$barcode', 'N/A', 1, 0, 1, $bestBeforeInDays, '$price')");
    }


    //Check if the given name includes any words that are associated with a product
    public function checkNameForTags($name) {
        $res = $this->db->query(self::generateQueryFromName($name));
        if ($row = $res->fetchArray()) {
            return $row["itemId"];
        } else {
            return 0;
        }
    }

    //Gets an array of BBuddy API keys
    public function getStoredApiKeys() {
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

    public function isValidApiKey($apiKey) {
        foreach ($this->getStoredApiKeys() as $key) {
            if ($apiKey === $key["key"]) {
                $this->db->exec("UPDATE ApiKeys SET lastused=datetime('now','localtime') WHERE id=" . $key["id"]);
                return true;
            }
        }
        return false;
    }


    //Generates API key
    public function generateApiKey() {
        $key = generateRandomString();
        $this->db->exec("INSERT INTO ApiKeys(key, lastused) VALUES('" . $key . "', 'Never');");
        return $key;
    }


    //Deletes API key
    public function deleteApiKey($id) {
        checkIfNumeric($id);
        $this->db->exec("DELETE FROM ApiKeys WHERE id='$id'");
    }


    //Deletes API key
    public function deleteAllApiKeys() {
        /** @noinspection SqlWithoutWhere */
        $this->db->exec("DELETE FROM ApiKeys");
    }


    //Get all stored logs
    public function getLogs() {
        $res  = $this->db->query('SELECT * FROM BarcodeLogs ORDER BY id DESC');
        $logs = array();
        while ($row = $res->fetchArray()) {
            array_push($logs, $row['log']);
        }
        return $logs;
    }


    public function saveError($errorMessage, $isFatal = true) {
        $verboseError = '<span style="color: red;">' . sanitizeString($errorMessage) . '</span> Please check your URL and API key in the settings menu!';
        $this->saveLog($verboseError, false, true);
        if ($isFatal) {
            echo("<b>" . $verboseError . "</b>");
        }
    }

    //Save a log
    public function saveLog($log, $isVerbose = false, $isError = false, $isDebug = false) {
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


    //Delete barcode from local db
    public function deleteBarcode($id) {
        $this->db->exec("DELETE FROM Barcodes WHERE id='$id'");
    }


    //Delete tag from local db 
    public function deleteTag($id) {
        $this->db->exec("DELETE FROM Tags WHERE id='$id'");
    }

    //Delete all saved barcodes
    public function deleteAll($section) {
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


    //Generates the SQL for word search
    private function generateQueryFromName($name) {
        $words = cleanNameForTagLookup($name);
        $i     = 0;
        $query = "SELECT itemId FROM Tags ";
        while ($i < sizeof($words)) {
            if ($i == 0) {
                $query = $query . "WHERE tag LIKE '" . $words[$i] . "'";
            } else {
                $query = $query . " OR tag LIKE '" . $words[$i] . "'";
            }
            $i++;
        }
        return $query;
    }

    /**
     * @return mixed
     */
    function getRawConfig() {
        return $this->db->query("SELECT * FROM BBConfig");
    }

    //Sets the config key with new value
    public function updateConfig($key, $value) {
        if (in_array($key, self::DB_INT_VALUES)) {
            checkIfNumeric($value);
        }
        $this->db->exec("UPDATE BBConfig SET value='" . $value . "' WHERE data='$key'");
        BBConfig::getInstance()[$key] = $value;
    }
}
