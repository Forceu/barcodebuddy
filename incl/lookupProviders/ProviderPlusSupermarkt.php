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
 * @since      File available since Release 1.6
 */


require_once __DIR__ . "/../api.inc.php";

class ProviderPlusSupermarkt extends LookupProvider {

    function __construct(string $apiKey = null) {
        parent::__construct($apiKey);
        $this->providerName       = 'Plus Supermarkt';
        $this->providerConfigKey  = 'LOOKUP_USE_PLUS';
        $this->ignoredResultCodes = array('404');
    }

    /**
     * Looks up a barcode
     * @param string $barcode The barcode to lookup
     * @return array|null Name of product, null if none found
     */
    public function lookupBarcode(string $barcode): ?array {
        if (!$this->isProviderEnabled()) {
            return null;
        }

        if (substr($barcode, 0, 2) == 21) {
            $barcode = str_pad(substr($barcode, 0, 6), 13, '0');
        }

        $url    = 'https://pls-sprmrkt-mw.prd.vdc1.plus.nl/api/v3/proxy/eancode/' . $barcode;
        $result = $this->execute($url, METHOD_GET);

        if (!isset($result['sku'])) {
            return null;
        }

        $productName = $result['name'];
        $genericName = null;

        if ($this->useGenericName) {
            $productUrl = 'https://pls-sprmrkt-mw.prd.vdc1.plus.nl/api/v3/product/' . $result['sku'];
            $productResult = $this->execute($productUrl, METHOD_GET);

            if (isset($productResult['wettelijke_naam']) && !empty($productResult['wettelijke_naam'])) {
                $genericName = $productResult['wettelijke_naam'];
            }
        }

        return self::createReturnArray($this->returnNameOrGenericName($productName, $genericName));
    }
}