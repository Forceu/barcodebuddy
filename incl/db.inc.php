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


//Initiate database and create if not existent
function initDb() {
    global $db;
    
    $db = new SQLite3(DATABASE_PATH);
    $db->busyTimeout(5000);
    $db->exec("CREATE TABLE IF NOT EXISTS Barcodes(id INTEGER PRIMARY KEY, barcode TEXT NOT NULL, name TEXT NOT NULL, possibleMatch INTEGER, amount INTEGER NOT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS Tags(id INTEGER PRIMARY KEY, tag TEXT NOT NULL, itemId INTEGER NOT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS TransactionState(id INTEGER PRIMARY KEY, currentState TINYINT NOT NULL, since INTEGER NOT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS BarcodeLogs(id INTEGER PRIMARY KEY, log TEXT NOT NULL)");
    $db->exec("INSERT INTO TransactionState(id,currentState,since) SELECT 1, 0, datetime('now','localtime') WHERE NOT EXISTS(SELECT 1 FROM TransactionState WHERE id = 1)");
}


//States to tell the script what to do with the barcodes that were scanned
const STATE_CONSUME = 0;
const STATE_CONSUME_SPOILED = 1;
const STATE_CONSUME_PURCHASE = 2;
const STATE_CONSUME_OPEN = 3;

const SECTION_KNOWN_BARCODES = "known";
const SECTION_UNKNOWN_BARCODES = "unknown";
const SECTION_LOGS = "log";

//Getting the state
function getTransactionState() {
    global $db;
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
            if ($differenceInMinutes > RESET_STATE_AFTER_MINUTES) {
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
    if ($isVerbose == false || MORE_VERBOSE_LOG == true) {
        $date = date('Y-m-d H:i:s');
        $db->exec("INSERT INTO BarcodeLogs(log) VALUES('" . $date . ": " . sanitizeString($log) . "')");
    }
}


//Delete barcode from local db
function deleteBarcode($id) {
    global $db;
    $db->exec("DELETE FROM Barcodes WHERE id='$id'");
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
    $db = null;
}

?>
