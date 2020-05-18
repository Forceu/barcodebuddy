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

    /**
     * Look up a barcode using providers
     * @param string $barcode Input barcode
     * @return string Returns product name or "N/A" if not found
     */
	public static function lookUp($barcode) {
		$useGenericName = BBConfig::getInstance()["USE_GENERIC_NAME"];

        $resultOpenFoodFacts = (new ProviderOpenFoodFacts($useGenericName))->lookupBarcode($barcode);
        if ($resultOpenFoodFacts != null)
            return $resultOpenFoodFacts;
        
        $resultUpcDb = (new ProviderUpcDb($useGenericName))->lookupBarcode($barcode);
        if ($resultUpcDb != null)
            return $resultUpcDb;

        return "N/A";
	}
}