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
 * @copyright  2021 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.8
 */

require_once __DIR__ . "/../configProcessing.inc.php";
require_once __DIR__ . "/../api.inc.php";
require_once __DIR__ . "/../db.inc.php";

class BarcodeFederation {
    public const  HOST             = "https://bb.bulling-it.de";
    public const  HOST_READABLE    = "Barcode Buddy servers";
    private const SECONDS_24_HOURS = 86400;

    /**
     * @return void
     */
    public static function doScheduledSyncBarcodes() {
        $config = BBConfig::getInstance();
        if (!$config["BBUDDY_SERVER_ENABLED"])
            return;
        if (time() < $config["BBUDDY_SERVER_NEXTSYNC"])
            return;
        DatabaseConnection::getInstance()->updateConfig("BBUDDY_SERVER_NEXTSYNC", strval(time() + rand(self::SECONDS_24_HOURS, self::SECONDS_24_HOURS * 2)));
        self::syncBarcodes();
    }

    public static function enableFederation(): void {
        $db = DatabaseConnection::getInstance();
        $db->updateConfig("BBUDDY_SERVER_ENABLED", "1");
        $db->updateConfig("BBUDDY_SERVER_NEXTSYNC", "0");
        $db->updateConfig("BBUDDY_SERVER_POPUPSHOWN", "1");
        $db->updateConfig("LOOKUP_USE_BBUDDY_SERVER", "1");
    }

    public static function disableFederation(): void {
        $db = DatabaseConnection::getInstance();
        $db->updateConfig("BBUDDY_SERVER_ENABLED", "0");
        $db->updateConfig("BBUDDY_SERVER_NEXTSYNC", "0");
        $db->updateConfig("BBUDDY_SERVER_POPUPSHOWN", "1");
        $db->updateConfig("LOOKUP_USE_BBUDDY_SERVER", "0");
    }

    /**
     * Sends all barcodes stored by Grocy to the BBuddy Server
     *
     * @return void
     */
    private static function syncBarcodes() {
        $url = self::HOST . "/add";

        $products = API::getAllProductsInfo();
        $barcodes = API::getAllBarcodes();

        if ($products == null || $barcodes == null)
            return;
        $items = array();

        foreach ($barcodes as $barcode) {
            $name = $products[$barcode["id"]]->name;
            if (strlen($name) > 1 && strlen($barcode["barcode"]) > 4)
                array_push($items, new ServerBarcode($barcode, $name));
        }
        $json = json_encode(array("ServerBarcodes" => $items));
        try {
            $curl = new CurlGenerator($url, METHOD_POST, $json, null, true, null, null, null, self::getUuidAsArray());
            $curl->execute(true);
        } catch (Exception $ignored) {
        }

    }

    /**
     * Votes a name
     * @param string $barcode
     * @param string $name
     * @return string "OK" if successful, otherwise error message
     */
    public static function voteName(string $barcode, string $name): string {
        return self::execRequest("vote", $barcode, $name);
    }


    /**
     * Reports a name
     * @param string $barcode
     * @param string $name
     * @return string "OK" if successful, otherwise error message
     */
    public static function reportName(string $barcode, string $name): string {
        return self::execRequest("report", $barcode, $name);
    }


    /**
     * Performs a GET request to the server
     * @param string $action
     * @param string $barcode
     * @param string $name
     * @return string "OK" if successful, otherwise error message
     */
    private static function execRequest(string $action, string $barcode, string $name): string {
        $url                = self::HOST . "/" . $action;
        $headers            = self::getUuidAsArray();
        $headers["name"]    = $name;
        $headers["barcode"] = $barcode;
        try {
            $curl = new CurlGenerator($url, METHOD_GET, null, null, true, null, null, null, $headers);
            $curl->execute(true);
        } catch (Exception $e) {
            return $e->getMessage();
        }
        return "OK";
    }

    /**
     * Checks if the server is available
     * @return bool
     */
    public static function isReachable(): bool {
        $url = self::HOST . "/ping";
        try {
            $curl = new CurlGenerator($url, METHOD_GET, null, null, true);
            return ($curl->execute() == "pong");
        } catch (Exception $ignored) {
            return false;
        }
    }

    /**
     * Returns a JS function to ping the server
     * @param string $resultFunction JS function name function(bool) that will be called once ping is complete
     * @return string
     */
    public static function getJsPing(string $resultFunction): string {
        $url = self::HOST . "/ping";
        return 'function isFederationOnline() {
        let xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange = function() {
            if (xmlhttp.readyState == 4) {
                let online = (xmlhttp.status == 200 && xmlhttp.responseText == "pong");
                '.$resultFunction.'(online);
            }
        }
        xmlhttp.open("GET", "'.$url.'", false);
        try {
            xmlhttp.send();
        } catch (e){
            console.log(e);
            '.$resultFunction.'(false);
        }
    }';
    }


    /**
     * Returns a JS function to receive the amount of stored barcodes
     * @param string $resultFunction JS function name function(int | bool, int) that will be called once amount is received
     * @return string
     */
    public static function getJsAmount(string $resultFunction): string {
        $url = self::HOST . "/amount";
        return 'function getFedereationAmount() {
        let xmlhttp = new XMLHttpRequest();
        const before = new Date();
        xmlhttp.onreadystatechange = function() {
            if (xmlhttp.readyState == 4) {
                 const now = new Date();
                if (xmlhttp.status  != 200) {
                    '.$resultFunction.'(false, 0);
                    return;
                }
                let response = parseInt(xmlhttp.responseText);
                if (isNaN(response)) {
                    '.$resultFunction.'(false, 0);
                    console.log("nö");
                    return;
                }
                '.$resultFunction.'(response, (now - before));
            }
        }
        xmlhttp.open("GET", "'.$url.'", false);
        try {
            xmlhttp.send();
        } catch (e){
            console.log(e);
            '.$resultFunction.'(false, 0);
        }
    }';
    }


    /**
     * Gets the amount of stored barcodes
     * @return string
     */
    public static function getCountStoredBarcodes(): string {
        $url = self::HOST . '/amount';
        try {
            $curl   = new CurlGenerator($url, METHOD_GET, null, null, true);
            $result = $curl->execute();
            if (is_string($result)) {
                return sanitizeString($result);
            }
        } catch (Exception $ignored) {
        }
        return "Unavailable";
    }

    /**
     * Gets the uuid of this instance
     * @return string
     * @throws DbConnectionDuringEstablishException
     */
    public static function getUuid(): string {
        $config = BBConfig::getInstance();
        $uuid   = $config["BBUDDY_SERVER_UUID"];
        if ($uuid == null) {
            $uuid = self::getRandomUuid();
            DatabaseConnection::getInstance()->updateConfig("BBUDDY_SERVER_UUID", $uuid);
        }
        return $uuid;
    }

    public static function getUuidAsArray(): array {
        return array("uuid" => self::getUuid());
    }

    /**
     * Generates a pseudo-random uuid
     * @return string
     */
    private static function getRandomUuid(): string {
        return md5(microtime() . rand(0, getrandmax()));
    }

    public static function chooseOtherBarcodeName(string $barcode, string $newName): string {
        $db = DatabaseConnection::getInstance();
        $db->updateUnrecognizedBarcodeName($barcode, $newName);
        return "OK";
    }
}

class ServerBarcode {
    public $name;
    public $barcode;

    /**
     * BarcodeServerItem constructor.
     * @param array $barcode
     * @param string $name
     */
    public function __construct(array $barcode, string $name) {
        $this->name    = $name;
        $this->barcode = $barcode["barcode"];
    }
}