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

class ProviderUpcDb extends LookupProvider {


    function __construct(string $apiKey = null) {
        parent::__construct($apiKey);
        $this->providerName       = "UPC Item DB";
        $this->providerConfigKey  = "LOOKUP_USE_UPC";
        $this->ignoredResultCodes = array(400, 404);
    }

    /**
     * Looks up a barcode
     * @param string $barcode The barcode to lookup
     * @return array|null Name of product, null if none found
     */
    public function lookupBarcode(string $barcode): ?array {
        if (!$this->isProviderEnabled())
            return null;

        $url    = "https://api.upcitemdb.com/prod/trial/lookup?upc=" . $barcode;
        $result = $this->execute($url);
        if (!isset($result["code"]) || $result["code"] != "OK")
            return null;

        if (isset($result["items"][0]["title"]) && $result["items"][0]["title"] != "") {
            return self::createReturnArray(sanitizeString($result["items"][0]["title"]));
        } else
            return null;
    }
}