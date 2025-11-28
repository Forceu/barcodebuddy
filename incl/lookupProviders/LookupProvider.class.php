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

abstract class LookupProvider {

    protected $useGenericName;
    protected $apiKey;
    protected $ignoredResultCodes = null;
    protected $id;

    function __construct(string $apiKey = null) {
        $this->apiKey = $apiKey;
        $this->useGenericName = (BBConfig::getInstance()["USE_GENERIC_NAME"] == "1");
        $this->ignoredResultCodes = array();
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
     * Sets the unique ID of the provider
     * @param string $id
     * @return void
     */
    public function setId(string $id): void {
        $this->id = $id;
    }

    /**
     * Returns the unique ID of the provider
     * @return string
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * Returns the human readable name of the provider
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Returns the description shown below the provider name in settings
     * @return string
     */
    abstract public function getDescription(): string;

    /**
     * Returns the configuration key for enabling/disabling the provider
     * @return string
     */
    abstract public function getConfigKey(): string;

    /**
     * Generates the configuration UI for this provider (excluding enablement checkbox)
     * Override this method if your provider needs custom configuration fields
     * @param UiEditor $html
     * @return string
     */
    public function getConfigHtml(UiEditor $html): string {
        return "";
    }

    /**
     * Returns true if the provider is enabled
     * @return bool
     */
    public function isEnabled(): bool {
        return BBConfig::getInstance()[$this->getConfigKey()] == "1";
    }

    /**
     * Returns the ID of the main configuration field (e.g. API key) to be toggled
     * @return string|null
     */
    public function getConfigFieldId(): ?string {
        return null;
    }

    /**
     * Saves the settings from the POST data
     * Override this method if your provider needs custom save logic
     * @param array $postData
     * @return void
     */
    public function saveSettings(array $postData): void {
        // Default implementation does nothing - generic saver handles standard fields
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
                    API::logError("Could not connect to " . $this->getName() . ".", false);
                    return null;
                case 'UnauthorizedException':
                    API::logError("Could not connect to " . $this->getName() . " - unauthorized");
                    return null;
                case 'InvalidJsonResponseException':
                    API::logError("Error parsing " . $this->getName() . " response: " . $e->getMessage(), false);
                    return null;
                case 'InvalidSSLException':
                    API::logError("Could not connect to " . $this->getName() . " - invalid SSL certificate");
                    return null;
                case 'InvalidParameterException':
                    API::logError("Internal error: Invalid parameter passed to " . $this->getName() . ".");
                    return null;
                case 'NotFoundException':
                    API::logError("Server " . $this->getName() . " reported path not found.");
                    return null;
                case 'LimitExceededException':
                    API::logError("Connection limits exceeded for " . $this->getName() . ".");
                    return null;
                case 'InternalServerErrorException':
                    API::logError($this->getName() . " reported internal error.");
                    return null;
                default:
                    API::logError("Unknown error with " . $this->getName() . ": " . $e->getMessage());
                    return null;
            }
        }
        return $result;
    }
}
