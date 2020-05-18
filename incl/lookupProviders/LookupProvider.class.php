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


require_once __DIR__ . "/ProviderOpenFoodFacts.php";

class LookupProvider {
    
    protected $useGenericName;
    protected $providerName;
    
    
    function __construct($useGenericName) {
        $this->useGenericName = $useGenericName;
    }
    
    /**
     * Looks up a barcode
     * @param  string $barcode     The barcode to lookup
     * @return null|string         Name of product, null if none found
     */
    public function lookupBarcode($barcode) {
        throw new Exception('lookupBarcode needs to be overriden!');
    }
    
    protected function execute($url) {
        $curl = new CurlGenerator($url, METHOD_GET, null, null, true);
        try {
            $result = $curl->execute(true);
        }
        catch (InvalidServerResponseException $e) {
            self::logError("Could not connect to " . $this->providerName . ".", false);
            return null;
        }
        catch (UnauthorizedException $e) {
            self::logError("Could not connect to " . $this->providerName . " - unauthorized");
            return null;
        }
        catch (InvalidJsonResponseException $e) {
            self::logError("Error parsing " . $this->providerName . " response: " . $e->getMessage(), false);
            return null;
        }
        catch (InvalidSSLException $e) {
            self::logError("Could not connect to " . $this->providerName . " - invalid SSL certificate");
            return null;
        }
        return $result;
    }
}

?>