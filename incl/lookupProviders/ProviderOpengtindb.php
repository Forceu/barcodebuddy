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

class ProviderOpengtindb extends LookupProvider {


    function __construct(string $apiKey = null) {
        parent::__construct($apiKey);
        $this->providerName       = "Open EAN / GTIN Database";
        $this->providerConfigKey  = "LOOKUP_USE_OPEN_GTIN_DATABASE";
        $this->ignoredResultCodes = array();
    }

    /**
     * Looks up a barcode
     * @param string $barcode The barcode to lookup
     * @return array|null Name of product, null if none found
     */
    public function lookupBarcode(string $barcode): ?array {
        $opoengtinKey = BBConfig::getInstance()['LOOKUP_OPENGTIN_KEY'];
        if (!$this->isProviderEnabled() || !$opoengtinKey)
            return null;

        $paddedBarcode = str_pad($barcode, 13, "0", STR_PAD_LEFT);
        $url           = "https://opengtindb.org/?ean=" . $paddedBarcode . "&cmd=query&queryid=" . $opoengtinKey;
        $result        = $this->execute($url, METHOD_GET, null, null, null, false);
        if ($result === false || $result === null) {
            return null;
        }
        if (strpos($result, "error") === false) {
            DatabaseConnection::getInstance()->saveError("Open EAN / GTIN Database API key is invalid!");
            return null;
        }
        if (strpos($result, "error=0") === false)
            return null;

        $matches = array();
        $name    = null;
        preg_match('/\nname=(.*)/', $result, $matches, PREG_UNMATCHED_AS_NULL);
        if (sizeof($matches) > 1) {
            $name = $matches[1];
        }

        $matches      = array();
        $nameDetailed = null;
        preg_match('/\ndetailname=(.*)/', $result, $matches, PREG_UNMATCHED_AS_NULL);
        if (sizeof($matches) > 1) {
            $nameDetailed = $matches[1];
        }

        return self::createReturnArray($this->returnNameOrGenericName($nameDetailed, $name));
    }
}