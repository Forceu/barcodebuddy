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
require_once __DIR__ . "/ProviderUpcDb.php";
require_once __DIR__ . "/ProviderJumbo.php";
require_once __DIR__ . "/ProviderUpcDatabase.php";
require_once __DIR__ . "/ProviderDebug.php";
require_once __DIR__ . "/ProviderAlbertHeijn.php";
require_once __DIR__ . "/ProviderPlusSupermarkt.php";
require_once __DIR__ . "/ProviderOpengtindb.php";
require_once __DIR__ . "/ProviderDiscogs.php";
require_once __DIR__ . "/ProviderFederation.php";

abstract class LookupProviderType
{
    const OpenFoodFacts = 0;
    const UpcDb = 1;
    const UpcDatabase = 2;
    const AlbertHeijn = 3;
    const Jumbo = 4;
    const OpenGtinDb = 5;
    const Federation = 6;
    const Plus = 7;
    const Discogs = 8;
}

class LookupProvider {

    protected $useGenericName;
    protected $apiKey;
    protected $providerName;
    protected $ignoredResultCodes = null;
    protected $providerConfigKey = null;

    function __construct(string $apiKey = null) {
        $this->useGenericName = BBConfig::getInstance()["USE_GENERIC_NAME"];
        $this->apiKey         = $apiKey;
    }

    /**
     * @return bool
     */
    protected function isProviderEnabled(): bool {
        if ($this->providerConfigKey == null)
            throw new Exception('providerConfigKey needs to be overriden!');
        return BBConfig::getInstance()[$this->providerConfigKey] == "1";
    }

    /**
     * Looks up a barcode
     * @param string $barcode The barcode to lookup
     * @return array|null Name of product, null if none found
     * @throws Exception
     */
    public function lookupBarcode(string $barcode): ?array {
        throw new Exception('lookupBarcode needs to be overriden!');
    }

    /**
     * Returns the generic or product name, depending what user set in config or if
     * a product / generic name is available
     * @param string|null $productName
     * @param string|null $genericName
     * @return string|null
     */
    public function returnNameOrGenericName(?string $productName, ?string $genericName): ?string {
        $productName = sanitizeString($productName);
        $genericName = sanitizeString($genericName);
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

    public static function createReturnArray(?string $name, ?string $alternateBBuddyName = null): ?array {
        if ($name == null)
            return null;
        return array("name" => $name, "altNames" => $alternateBBuddyName);
    }


    /**
     * @param string $url
     * @param string $method
     * @param array|null $formdata
     * @param string|null $userAgent
     * @param array|null $headers
     * @param bool $decodeJson
     * @param string|null $jsonData
     * @return bool|mixed|string|null
     */
    protected function execute(string $url, string $method = METHOD_GET, array $formdata = null, string $userAgent = null, ?array $headers = null, bool $decodeJson = true, string $jsonData = null) {
        $curl = new CurlGenerator($url, $method, $jsonData, null, true, $this->ignoredResultCodes, $formdata, $userAgent, $headers);
        try {
            $result = $curl->execute($decodeJson);
        } catch (Exception $e) {
            $class = get_class($e);
            switch ($class) {
                case 'InvalidServerResponseException':
                    API::logError("Could not connect to " . $this->providerName . ".", false);
                    return null;
                case 'UnauthorizedException':
                    API::logError("Could not connect to " . $this->providerName . " - unauthorized");
                    return null;
                case 'InvalidJsonResponseException':
                    API::logError("Error parsing " . $this->providerName . " response: " . $e->getMessage(), false);
                    return null;
                case 'InvalidSSLException':
                    API::logError("Could not connect to " . $this->providerName . " - invalid SSL certificate");
                    return null;
                case 'InvalidParameterException':
                    API::logError("Internal error: Invalid parameter passed to " . $this->providerName . ".");
                    return null;
                case 'NotFoundException':
                    API::logError("Server " . $this->providerName . " reported path not found.");
                    return null;
                case 'LimitExceededException':
                    API::logError("Connection limits exceeded for " . $this->providerName . ".");
                    return null;
                case 'InternalServerErrorException':
                    API::logError($this->providerName . " reported internal error.");
                    return null;
                default:
                    API::logError("Unknown error with " . $this->providerName . ": " . $e->getMessage());
                    return null;
            }
        }
        return $result;
    }
}
