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

class ProviderOpenFoodFacts extends LookupProvider {

    private $result;

    function __construct(string $apiKey = null) {
        parent::__construct($apiKey);
        $this->providerName      = "OpenFoodFacts";
        $this->providerConfigKey = "LOOKUP_USE_OFF";
    }

    /**
     * Looks up a barcode
     * @param string $barcode The barcode to lookup
     * @return array|null Name of product, null if none found
     */
    public function lookupBarcode(string $barcode): ?array {
        if (!$this->isProviderEnabled())
            return null;

        global $CONFIG;


        $url    = "https://world.openfoodfacts.org/api/v0/product/" . $barcode . ".json";
        $result = $this->execute($url);
        if (!isset($result["status"]) || $result["status"] !== 1)
            return null;

        $this->result = $result;

        return self::createReturnArray($this->returnNameOrGenericName($this->getProductName($CONFIG->DEFAULT_LOOKUP_LANGUAGE), $this->getGenericName($CONFIG->DEFAULT_LOOKUP_LANGUAGE)));
    }

    private function getGenericName(string $defaultLanguage): ?string {
        if (isset($this->result["product"]["generic_name_" . $defaultLanguage]) && $this->result["product"]["generic_name_" . $defaultLanguage] != "") {
            return sanitizeString($this->result["product"]["generic_name_" . $defaultLanguage]);
        }
        if (isset($this->result["product"]["generic_name"]) && $this->result["product"]["generic_name"] != "") {
            return sanitizeString($this->result["product"]["generic_name"]);
        }
        return null;
    }

    private function getProductName(string $defaultLanguage): ?string {
        if (isset($this->result["product"]["product_name_" . $defaultLanguage]) && $this->result["product"]["product_name_" . $defaultLanguage] != "") {
            return sanitizeString($this->result["product"]["product_name_" . $defaultLanguage]);
        }
        if (isset($this->result["product"]["product_name"]) && $this->result["product"]["product_name"] != "") {
            return sanitizeString($this->result["product"]["product_name"]);
        }
        return null;
    }
}