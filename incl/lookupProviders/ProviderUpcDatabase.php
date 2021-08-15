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
 * @since      File available since Release 1.5
 */


require_once __DIR__ . "/../api.inc.php";

class ProviderUpcDatabase extends LookupProvider {


    function __construct(string $apiKey = null) {
        parent::__construct($apiKey);
        $this->providerName       = "UPC Database";
        $this->providerConfigKey  = "LOOKUP_USE_UPC_DATABASE";
        $this->ignoredResultCodes = array();
    }

    /**
     * Looks up a barcode
     * @param string $barcode The barcode to lookup
     * @return array|null Name of product, null if none found
     */
    public function lookupBarcode(string $barcode): ?array {
        $upcdb_key = BBConfig::getInstance()['LOOKUP_UPC_DATABASE_KEY'];
        if (!$this->isProviderEnabled() || !$upcdb_key)
            return null;

        $paddedBarcode = str_pad($barcode, 13, "0", STR_PAD_LEFT);
        $url           = "https://api.upcdatabase.org/product/" . $paddedBarcode . "?apikey=" . $upcdb_key;
        $result        = $this->execute($url);
        if (!isset($result["success"]))
            return null;
        if (isset($result["error"])) {
            if (isset($result["error"]["message"]) && strpos($result["error"]["message"], "API Key is invalid.") !== false)
                DatabaseConnection::getInstance()->saveError("UPC Database API key is invalid!");
            return null;
        }
        if (!isset($result["description"]) && !isset($result["title"]))
            return null;

        if (!empty($result["title"])) {
            return self::createReturnArray(sanitizeString($result["title"]));
        } else if (!empty($result["description"])) {
            return self::createReturnArray(sanitizeString($result["description"]));
        }
        return null;
    }
}