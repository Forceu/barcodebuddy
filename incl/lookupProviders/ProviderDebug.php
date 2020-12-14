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

class ProviderDebug extends LookupProvider {

    /** @psalm-assert false IS_ENABLED */
    const IS_ENABLED    = false;
    const RETURN_STRING = "This is a test result";

    function __construct($apiKey = null) {
        parent::__construct($apiKey);
        $this->providerName      = "UPC Item DB";
        $this->providerConfigKey = "USE_DEBUG_LOOKUP";
    }

    function isProviderEnabled(): bool {
        return IS_ENABLED;
    }

    public function lookupBarcode($barcode) {
        return self::RETURN_STRING;
    }
}