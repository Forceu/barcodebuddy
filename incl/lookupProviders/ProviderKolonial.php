<?php
require_once __DIR__ . "/../api.inc.php";

class ProviderKolonial extends LookupProvider {
    const KOLONIAL_URI = 'https://kolonial.no/api/v1/';

    public $headers = [
        'cookie' => 'notLoggedIn',
        // 'content-type' => 'application/json',
        // 'accept' => 'application/json',
        'user-agent' => 'OleKennethBratholt_Test/1.0',
        'X-Client-Token' => 'JCVJXK8n1puRFeTAdVLwDRHIYKakM2ixybJZiunVV73MEtFjf9',
    ];

    function __construct($apiKey = null) {
        parent::__construct($apiKey);
        $this->providerName       = "Kolonial";
        $this->providerConfigKey  = "LOOKUP_USE_BBUDDY_SERVER";
    }

    private function login(): void {
        $url = self::KOLONIAL_URI . 'user/login/';
        $data = [
            'username' => 'ok@rait.no',
            'password' => 'FKx-GMr-5Hw-pc8'
        ];

        $res = $this->execute($url, METHOD_POST, null, null, $this->headers, true, json_encode($data));

        $this->headers['cookie'] = $res['sessionid'];
    }

    /**
     * Looks up a barcode
     * @param string $barcode The barcode to lookup
     * @return array|null Name of product, null if none found
     */
    public function lookupBarcode(string $barcode): ?array {
        $this->login();
        $url = self::KOLONIAL_URI . 'search/?q=' . $barcode;

        $result = $this->execute($url, METHOD_GET, null, null, $this->headers);

        if ($result['products'] && $result['products'][0]) {
            return array(
                "name" => sanitizeString($result['products'][0]['name']),
                "altNames" => []
            );
        }
        return null;
    }
}