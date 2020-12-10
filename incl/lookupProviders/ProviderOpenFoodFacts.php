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

    function __construct($apiKey = null) {
        parent::__construct($apiKey);
        $this->providerName      = "OpenFoodFacts";
        $this->providerConfigKey = "LOOKUP_USE_OFF";
    }

    /**
     * Looks up a barcode
     * @param string $barcode The barcode to lookup
     * @return null|string         Name of product, null if none found
     */
    public function lookupBarcode($barcode) {
        if (!$this->isProviderEnabled())
            return null;

        $url    = "https://world.openfoodfacts.org/api/v0/product/" . $barcode . ".json";
        $result = $this->execute($url);
        if (!isset($result["status"]) || $result["status"] !== 1)
            return null;

        $genericName = null;
        $productName = null;
        if (isset($result["product"]["generic_name"]) && $result["product"]["generic_name"] != "") {
            $genericName = sanitizeString($result["product"]["generic_name"]);
        }
        if (isset($result["product"]["product_name"]) && $result["product"]["product_name"] != "") {
            $productName = sanitizeString($result["product"]["product_name"]);
        }

        if ($this->useGenericName) {
            if ($genericName != null)
                return $genericName;
            if ($productName != null)
                return $productName;
        } else {
            if ($productName != null)
                return $productName;
            if ($genericName != null)
                return $genericName;
        }
        return null;
    }
}