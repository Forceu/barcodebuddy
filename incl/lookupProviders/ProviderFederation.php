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


require_once __DIR__ . "/../api.inc.php";

class ProviderFederation extends LookupProvider {


    function __construct(string $apiKey = null) {
        parent::__construct($apiKey);
        $this->providerName       = "Barcode Buddy Cloud";
        $this->providerConfigKey  = "LOOKUP_USE_BBUDDY_SERVER";
        $this->ignoredResultCodes = array(529);
    }

    /**
     * Looks up a barcode
     * @param string $barcode The barcode to lookup
     * @return array|null Name of product, null if none found
     */
    public function lookupBarcode(string $barcode): ?array {
        if (!$this->isProviderEnabled())
            return null;

        $url                = BarcodeFederation::HOST . "/get";
        $headers            = BarcodeFederation::getUuidAsArray();
        $headers["barcode"] = $barcode;

        $result = $this->execute($url, METHOD_GET, null, null, $headers);
        if (!isset($result["Result"]) || $result["Result"] != "OK")
            return null;

        $foundName = sanitizeString($result["FoundNames"][0]);
        $names     = array();
        foreach ($result["FoundNames"] as $name)
            array_push($names, sanitizeString($name));
        $altNames = json_encode($names);
        return self::createReturnArray($foundName, $altNames);
    }
}