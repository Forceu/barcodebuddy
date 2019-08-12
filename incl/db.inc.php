<?php
/**
 * Barcode Buddy for Grocy
 *
 * PHP version 7
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */


/**
 * Helper file for database connection
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */
require_once __DIR__ . "/processing.inc.php";
require_once __DIR__ . "/PluginLoader.php";


const BB_VERSION = "1220";
const BB_VERSION_READABLE = "1.2.2.0";

const DEFAULT_VALUES      = array("DEFAULT_BARCODE_C" => "BBUDDY-C",
				 "DEFAULT_BARCODE_CS" => "BBUDDY-CS",
				 "DEFAULT_BARCODE_P" => "BBUDDY-P",
				 "DEFAULT_BARCODE_O" => "BBUDDY-O",
				 "DEFAULT_BARCODE_GS" => "BBUDDY-I",
				 "DEFAULT_REVERT_TIME" => "10",
				 "DEFAULT_REVERT_SINGLE" => "1",
				 "DEFAULT_MORE_VERBOSE" => "1",
				 "DEFAULT_GROCY_API_URL" => null,
				 "DEFAULT_GROCY_API_KEY" => null,
				 "DEFAULT_WS_USE" => "false",
				 "DEFAULT_WS_PORT" => "47631",
				 "DEFAULT_WS_PORT_EXT" => "47631",
				 "DEFAULT_WS_SSL_USE" => "0",
				 "DEFAULT_WS_SSL_URL" => null,
				 "DEFAULT_SHOPPINGLIST_REMOVE" => "1");


const DB_INT_VALUES      = array("REVERT_TIME", "WS_PORT", "WS_PORT_EXT");


//Initiate database and create if not existent
function initDb() {
    global $db;
    global $BBCONFIG;
     
    checkPermissions();
    $db = new SQLite3(DATABASE_PATH);
    $db->busyTimeout(5000);
    $db->exec("CREATE TABLE IF NOT EXISTS Barcodes(id INTEGER PRIMARY KEY, barcode TEXT NOT NULL, name TEXT NOT NULL, possibleMatch INTEGER, amount INTEGER NOT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS Tags(id INTEGER PRIMARY KEY, tag TEXT NOT NULL, itemId INTEGER NOT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS TransactionState(id INTEGER PRIMARY KEY, currentState TINYINT NOT NULL, since INTEGER NOT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS BarcodeLogs(id INTEGER PRIMARY KEY, log TEXT NOT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS BBConfig(id INTEGER PRIMARY KEY, data TEXT UNIQUE NOT NULL, value TEXT NOT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS ChoreBarcodes(id INTEGER PRIMARY KEY, choreId INTEGER UNIQUE, barcode TEXT NOT NULL )");
    insertDefaultValues();
    getConfig();
    $previousVersion = $BBCONFIG["version"];
    if ($previousVersion < BB_VERSION) {
        upgradeBarcodeBuddy($previousVersion);
        getConfig();
    }
}


function insertDefaultValues() {
    global $db;
    $db->exec("INSERT INTO TransactionState(id,currentState,since) SELECT 1, 0, datetime('now','localtime') WHERE NOT EXISTS(SELECT 1 FROM TransactionState WHERE id = 1)");
    $db->exec("INSERT INTO BBConfig(id,data,value) SELECT 1, \"version\", \"" . BB_VERSION . "\" WHERE NOT EXISTS(SELECT 1 FROM BBConfig WHERE id = 1)");
    foreach (DEFAULT_VALUES as $key => $value) {
       $name=str_replace("DEFAULT_","",$key);
       $db->exec("INSERT INTO BBConfig(data,value) SELECT \"".$name."\", \"" . $value . "\" WHERE NOT EXISTS(SELECT 1 FROM BBConfig WHERE data = '$name')");
} 
}


function getConfig() {
    global $db;
    global $BBCONFIG;
    $BBCONFIG            = array();
    $res = $db->query("SELECT * FROM BBConfig");
    while ($row = $res->fetchArray()) {
        $BBCONFIG[$row['data']]      = $row['value'];
    }
    if (sizeof($BBCONFIG)==0) {
        die("DB Error: Could not get configuration");
    }
}

function updateConfig($key,$value) {
    global $db;
    if (in_array($key,DB_INT_VALUES)) {
        checkIfNumeric($value);
}
    $db->exec("UPDATE BBConfig SET value='".sanitizeString($value)."' WHERE data='$key'");
}


function checkPermissions() {
    if (file_exists(DATABASE_PATH)) {
        if (!is_writable(DATABASE_PATH)) {
            die("DB Error: Database file is not writable");
        }
    } else {
        if (!is_writable(dirname(DATABASE_PATH))) {
            die("DB Error: Database file cannot be created, as folder is not writable. Please check your permissions.<br>
                 Have a look at this link to find out how to do this:
                 <a href='https://github.com/olab/Open-Labyrinth/wiki/How-do-I-make-files-and-folders-writable-for-the-web-server%3F'>".
                 "How do I make files and folders writable for the web server?</a>");
        }
    }
}

function upgradeBarcodeBuddy($previousVersion) {
    global $db;
    global $BBCONFIG;
    //Place for future update protocols
    $db->exec("UPDATE BBConfig SET value='" . BB_VERSION . "' WHERE data='version'");
    if ($previousVersion < 1211) {
        getConfig();
        updateConfig("BARCODE_C", strtoupper($BBCONFIG["BARCODE_C"]));
        updateConfig("BARCODE_O", strtoupper($BBCONFIG["BARCODE_O"]));
        updateConfig("BARCODE_P", strtoupper($BBCONFIG["BARCODE_P"]));
        updateConfig("BARCODE_CS", strtoupper($BBCONFIG["BARCODE_CS"]));
    }
}

//States to tell the script what to do with the barcodes that were scanned
const STATE_CONSUME = 0;
const STATE_CONSUME_SPOILED = 1;
const STATE_PURCHASE = 2;
const STATE_OPEN = 3;
const STATE_GETSTOCK = 4;

const SECTION_KNOWN_BARCODES = "known";
const SECTION_UNKNOWN_BARCODES = "unknown";
const SECTION_LOGS = "log";

//Getting the state TODO change date
function getTransactionState() {
    global $db;
    global $BBCONFIG;

    $res = $db->query("SELECT * FROM TransactionState");
    if ($row = $res->fetchArray()) {
        $state = $row["currentState"];
        $since = $row["since"];
        if ($state == STATE_CONSUME) {
            return STATE_CONSUME;
        } else {
            $stateSet            = strtotime($since);
            $now                 = strtotime('now');
            $differenceInMinutes = round(abs($now - $stateSet) / 60, 0);
            if ($differenceInMinutes > $BBCONFIG["REVERT_TIME"]) {
                setTransactionState(STATE_CONSUME);
                return STATE_CONSUME;
            } else {
                return $state;
            }
        }
    } else {
        die("DB Error");
    }
}



//Setting the state
function setTransactionState($state) {
    global $db;
    $db->exec("UPDATE TransactionState SET currentState=$state, since=datetime('now','localtime')");
}

//Gets an array of locally stored barcodes
function getStoredBarcodes() {
    global $db;
    $res                 = $db->query('SELECT * FROM Barcodes');
    $barcodes            = array();
    $barcodes["known"]   = array();
    $barcodes["unknown"] = array();
    while ($row = $res->fetchArray()) {
        $item            = array();
        $item['id']      = $row['id'];
        $item['barcode'] = $row['barcode'];
        $item['amount']  = $row['amount'];
        $item['name']    = $row['name'];
        $item['match']   = $row['possibleMatch'];
        if ($row['name'] != "N/A") {
            array_push($barcodes["known"], $item);
        } else {
            array_push($barcodes["unknown"], $item);
        }
    }
    return $barcodes;
}



//Gets an array of locally stored tags
function getStoredTags() {
    global $db;
    $res                 = $db->query('SELECT * FROM Tags');
    $tags                = array();
    while ($row = $res->fetchArray()) {
        $item            = array();
        $item['id']      = $row['id'];
        $item['name']    = $row['tag'];
        $item['itemId']  = $row['itemId'];
        $item['item']    = "";
        array_push($tags, $item);
    }
    return $tags;
}



//Gets an array of locally stored chore barcodes
function getStoredChoreBarcodes() {
    global $db;
    $res                 = $db->query('SELECT * FROM ChoreBarcodes');
    $chores                = array();
    while ($row = $res->fetchArray()) {
        $item            = array();
        $item['id']      = $row['id'];
        $item['choreId']    = $row['choreId'];
        $item['barcode']  = $row['barcode'];
        array_push($chores, $item);
    }
    return $chores;
}


function updateChoreBarcode($choreId, $choreBarcode) {
    global $db;
    checkIfNumeric($choreId);
    $db->exec("REPLACE INTO ChoreBarcodes(choreId, barcode) VALUES(" . $choreId . ", '" . str_replace('&#39;', "",sanitizeString($choreBarcode)) . "')");
}


function deleteChoreBarcode($id) {
    global $db;
    checkIfNumeric($id);
    $db->exec("DELETE FROM ChoreBarcodes WHERE choreId='$id'");
}

function isChoreBarcode($barcode) {
    return (getChoreBarcode($barcode)!=null);
}


function getChoreBarcode($barcode) {
    global $db;
    $res = $db->query("SELECT * FROM ChoreBarcodes WHERE barcode='".sanitizeString($barcode)."'");
    if ($row = $res->fetchArray()) {
        return $row;
    } else {
        return null;
    }
}


function isUnknownBarcodeAlreadyStored($barcode) {
    global $db;
    $count = $db->querySingle("SELECT COUNT(*) as count FROM Barcodes WHERE barcode='$barcode'");
    return ($count != 0);
}

function addQuantitiyToUnknownBarcode($barcode, $amount) {
    global $db;
    $db->exec("UPDATE Barcodes SET amount = amount + $amount WHERE barcode = '$barcode'");
}

function insertUnrecognizedBarcode($barcode, $productname = "N/A", $amount = 1, $match = 0) {
    global $db;
    $db->exec("INSERT INTO Barcodes(barcode, name, amount, possibleMatch) VALUES('$barcode', '$productname', $amount, $match)");
}


//Check if the given name includes any words that are associated with a product
function checkNameForTags($name) {
    global $db;
    $res = $db->query(generateQueryFromName($name));
    if ($row = $res->fetchArray()) {
        return $row["itemId"];
    } else {
        return 0;
    }
}


//Get all stored logs
function getLogs() {
    global $db;
    $res  = $db->query('SELECT * FROM BarcodeLogs ORDER BY id DESC');
    $logs = array();
    while ($row = $res->fetchArray()) {
        array_push($logs, $row['log']);
    }
    return $logs;
}


//Save a log
function saveLog($log, $isVerbose = false) {
    global $db;
    global $BBCONFIG;
    if ($isVerbose == false || $BBCONFIG["MORE_VERBOSE"] == true) {
        $date = date('Y-m-d H:i:s');
        $db->exec("INSERT INTO BarcodeLogs(log) VALUES('" . $date . ": " . sanitizeString($log) . "')");
    }
}


//Delete barcode from local db
function deleteBarcode($id) {
    global $db;
    $db->exec("DELETE FROM Barcodes WHERE id='$id'");
}


//Delete tag from local db
function deleteTag($id) {
    global $db;
    $db->exec("DELETE FROM Tags WHERE id='$id'");
}

function deleteAll($section) {
    global $db;
    switch ($section) {
        case SECTION_KNOWN_BARCODES:
            $db->exec("DELETE FROM Barcodes WHERE name IS NOT 'N/A'");
            break;
        case SECTION_UNKNOWN_BARCODES:
            $db->exec("DELETE FROM Barcodes WHERE name='N/A'");
            break;
        case SECTION_LOGS:
            $db->exec("DELETE FROM BarcodeLogs");
            break;
    }
}


//Generates the SQL for word search
function generateQueryFromName($name) {
    $words = explode(" ", $name);
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


// Initiates the database variable
if (!isset($db)) {
    initDb();
}

?>
