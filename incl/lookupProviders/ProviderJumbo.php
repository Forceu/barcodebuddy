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

class ProviderJumbo extends LookupProvider {

    function __construct(string $apiKey = null) {
        parent::__construct($apiKey);
        $this->providerName       = "Jumbo Group";
        $this->providerConfigKey  = "LOOKUP_USE_JUMBO";
        $this->ignoredResultCodes = array();
    }

    /**
     * Looks up a barcode
     * @param string $barcode The barcode to lookup
     * @return array|null Name of product, null if none found
     */
    public function lookupBarcode(string $barcode): ?array {
        if (!$this->isProviderEnabled())
            return null;

        $userAgent = "Mozilla/5.0 (Linux; Android 11; SM-N976N Build/RP1A.200720.012; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/80.0.3987.163 Whale/1.0.0.0 Crosswalk/25.80.14.24 Mobile Safari/537.36 NAVER(inapp; search; 598; 11.0.6)";

        $url    = "https://mobileapi.jumbo.com/v17/search?q=" . $barcode;
        $result = $this->execute($url, "GET", null, $userAgent);
        if (!isset($result["products"]) || !isset($result["products"]["data"]) || !isset($result["products"]["total"]) || $result["products"]["total"] == "O")
            return null;

        if (isset($result["products"]["data"][0]["title"]) && $result["products"]["data"][0]["title"] != "") {
            return self::createReturnArray(sanitizeString($result["products"]["data"][0]["title"]));
        } else
            return null;
    }
}