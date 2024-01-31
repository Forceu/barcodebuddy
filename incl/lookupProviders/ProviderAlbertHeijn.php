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

class ProviderAlbertHeijn extends LookupProvider {
    const USER_AGENT = "Appie/8.22.3";

    protected $db;

    function __construct(string $apiKey = null) {
        parent::__construct($apiKey);
        $this->providerName       = "Albert Heijn";
        $this->providerConfigKey  = "LOOKUP_USE_AH";
        $this->ignoredResultCodes = array("404");
        $this->db                 = DatabaseConnection::getInstance();
    }

    /**
     * Looks up a barcode
     * @param string $barcode The barcode to lookup
     * @return array|null Name of product, null if none found
     */
    public function lookupBarcode(string $barcode): ?array {
        if (!$this->isProviderEnabled())
            return null;
        if (strlen($barcode) >= 20)
	        return null;
        
        $authkey = $this->getAuthToken();
        if ($authkey == null)
            return null;

        $headers = array('X-Application' => 'AHWEBSHOP', 'Authorization' => 'Bearer ' . $authkey);
        $url     = "https://api.ah.nl/mobile-services/product/search/v1/gtin/" . $barcode;
        $result  = $this->execute($url, METHOD_GET, null, self::USER_AGENT, $headers);
        if (isset($result["title"]))
            return self::createReturnArray(sanitizeString($result["title"]));
        else
            return null;
    }

    private function getAuthToken(): ?string {
        $jsonData = $this->db->getLookupProviderData(LookupProviderType::AlbertHeijn);

        if ($jsonData == null) {
            $newAuthToken = $this->newAuthToken();
            $this->updateAuthToken($newAuthToken);

            return sanitizeString($newAuthToken["access_token"]);
        }

        $data = json_decode($jsonData, true);

        if ($data["expires"] < time()) {
            $newAuthToken = $this->refreshToken($data["refresh_token"]);
            $this->updateAuthToken($newAuthToken);

            return sanitizeString($newAuthToken["access_token"]);
        }

        return sanitizeString($data["access_token"]);
    }

    private function updateAuthToken(array $authToken): void {
        $data = array("access_token" => $authToken["access_token"], "refresh_token" => $authToken["refresh_token"], "expires" => time() + $authToken["expires_in"]);
        $jsonData = json_encode($data);
        
        $this->db->upsertLookupProviderData(LookupProviderType::AlbertHeijn, $jsonData);
    }

    private function newAuthToken(): ?array {
        $json            = '{"clientId": "appie"}';
        $url             = "https://api.ah.nl/mobile-auth/v1/auth/token/anonymous";
        $authkeyResponse = $this->execute($url, METHOD_POST, null, self::USER_AGENT, null, true, $json);
    
        if (!isset($authkeyResponse["access_token"]))
            return null;

        return $authkeyResponse;
    }

    private function refreshToken(string $refreshToken): ?array {
        $json            = '{"clientId": "appie", "refreshToken": "' . $refreshToken . '"}';
        $url             = "https://api.ah.nl/mobile-auth/v1/auth/token/refresh";
        $authkeyResponse = $this->execute($url, METHOD_POST, null, self::USER_AGENT, null, true, $json);

        if ($authkeyResponse == null) {
            $authkeyResponse = $this->newAuthToken();
        }

        if (!isset($authkeyResponse["access_token"])) {
            return null;
        }

        return $authkeyResponse;
    }
}
