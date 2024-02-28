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

require_once __DIR__ . "/LookupProvider.class.php";
require_once __DIR__ . "/../db.inc.php";
require_once __DIR__ . "/../config.inc.php";


class BarcodeLookup {

    private const USE_DEBUG_PROVIDER = false;

    private static $providers = array(
        LOOKUP_ID_OPENFOODFACTS => "ProviderOpenFoodFacts",
        LOOKUP_ID_UPCDB         => "ProviderUpcDb",
        LOOKUP_ID_UPCDATABASE   => "ProviderUpcDatabase",
        LOOKUP_ID_ALBERTHEIJN   => "ProviderAlbertHeijn",
        LOOKUP_ID_PLUS          => "ProviderPlusSupermarkt",
        LOOKUP_ID_JUMBO         => "ProviderJumbo",
        LOOKUP_ID_OPENGTINDB    => "ProviderOpengtindb",
        LOOKUP_ID_DISCOGS       => "ProviderDiscogs",
        LOOKUP_ID_FEDERATION    => "ProviderFederation"
    );

    /**
     * Look up a barcode using providers
     * @param string $barcode Input barcode
     * @return array Returns product name array or null if not found
     */
    public static function lookUp(string $barcode): ?array {
        if (self::USE_DEBUG_PROVIDER) {
            return (new ProviderDebug)->lookupBarcode($barcode);
        }
        $config       = BBConfig::getInstance();
        $orderAsArray = explode(",", $config["LOOKUP_ORDER"]);
        foreach ($orderAsArray as $orderId) {
            $result = (new self::$providers[$orderId]())->lookupBarcode($barcode);
            if ($result != null)
                return $result;
        }
        return null;
    }
}
