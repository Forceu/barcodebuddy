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
 *
 * This file added by Paul Warren 2024
 */


require_once __DIR__ . "/../api.inc.php";

class ProviderDiscogs extends LookupProvider {


    function __construct(string $apiKey = null) {
        parent::__construct($apiKey);
        $this->providerName       = "Discogs DB";
        $this->providerConfigKey  = "LOOKUP_USE_DISCOGS";
        $this->ignoredResultCodes = array(400, 404);
    }

    /**
     * Looks up a barcode
     * @param string $barcode The barcode to lookup
     * @return array|null Name of product, null if none found
     */
    public function lookupBarcode(string $barcode): ?array {
                  
        $discogs_token = BBConfig::getInstance()['LOOKUP_DISCOGS_TOKEN'];
        if (!$this->isProviderEnabled())
            return null;

        $url    = "https://api.discogs.com/database/search?q=". $barcode . "&token=" . $discogs_token;
        $result = $this->execute($url);
     
        if ($result["pagination"][0]["items"] == "0")
            return null;

        if (isset($result["results"][0]["title"]) && $result["results"][0]["title"] != "") {
            return self::createReturnArray(sanitizeString($result["results"][0]["title"]));
        } else
            return null;
    }
}
