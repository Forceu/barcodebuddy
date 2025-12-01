<?php

/**
 * Custom Webhooks Lookup Provider
 *
 * PHP version 7
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 * @author     Wiktor Jarka
 * @copyright  2025 Wiktor Jarka
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 */

require_once __DIR__ . "/../api.inc.php";

class ProviderCustomWebhooks extends LookupProvider {

    function __construct(string $apiKey = null) {
        parent::__construct($apiKey);
    }

    public function getName(): string {
        return "Custom Webhooks";
    }

    public function getDescription(): string {
        return "Query custom webhooks for barcode lookup";
    }

    public function getConfigKey(): string {
        return "LOOKUP_USE_CUSTOM_WEBHOOKS";
    }

    public function getConfigHtml(UiEditor $html): string {
        $config = BBConfig::getInstance();
        $webhooks = $config["LOOKUP_CUSTOM_WEBHOOKS_URLS"] ?? "";
        
        return '<div class="mdl-textfield mdl-js-textfield mdl-textfield--floating-label" style="width: 100%;">
                    <textarea class="mdl-textfield__input" type="text" rows="5" name="LOOKUP_CUSTOM_WEBHOOKS_URLS" id="LOOKUP_CUSTOM_WEBHOOKS_URLS">' . $webhooks . '</textarea>
                    <label class="mdl-textfield__label" for="LOOKUP_CUSTOM_WEBHOOKS_URLS">Webhooks (one per line)</label>
                </div>
                <div style="margin-top: 8px; font-size: 0.9em; color: #757575;">
                    <b>Format:</b> GET request with <code>?barcode=...</code> parameter. Expected JSON response: <code>{"lookups": [{"name": "...", "genericName": "..."}]}</code>
                </div>';
    }

    public function saveSettings(array $postData): void {
        if (isset($postData["LOOKUP_CUSTOM_WEBHOOKS_URLS"])) {
            $webhooks = sanitizeString($postData["LOOKUP_CUSTOM_WEBHOOKS_URLS"]);
            
            $urls = explode("\n", $webhooks);
            $validUrls = [];
            foreach ($urls as $url) {
                $url = trim($url);
                if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                    $validUrls[] = $url;
                }
            }
            $finalWebhooks = implode("\n", $validUrls);
            
            $db = DatabaseConnection::getInstance();
            $db->updateConfig("LOOKUP_CUSTOM_WEBHOOKS_URLS", $finalWebhooks);
        }
    }

    public function lookupBarcode(string $barcode): ?array {
        if (!$this->isEnabled()) {
            return null;
        }

        $config = BBConfig::getInstance();
        $webhooks = $config["LOOKUP_CUSTOM_WEBHOOKS_URLS"] ?? "";
        $urls = explode("\n", $webhooks);

        foreach ($urls as $url) {
            $url = trim($url);
            if (empty($url)) {
                continue;
            }

            // Append barcode
            if (strpos($url, '?') !== false) {
                $fullUrl = $url . "&barcode=" . $barcode;
            } else {
                $fullUrl = $url . "?barcode=" . $barcode;
            }

            $result = $this->execute($fullUrl);
            
            if ($result && isset($result["lookups"]) && is_array($result["lookups"]) && count($result["lookups"]) > 0) {
                $firstLookup = $result["lookups"][0];
                $name = $firstLookup["name"] ?? null;
                $genericName = $firstLookup["genericName"] ?? null;
                
                $productName = $this->returnNameOrGenericName($name, $genericName);
                
                if ($productName) {
                    // Handle altNames
                    $altNames = null;
                    if (count($result["lookups"]) > 1) {
                        $altNameList = [];
                        for ($i = 1; $i < count($result["lookups"]); $i++) {
                            $l = $result["lookups"][$i];
                            $n = $this->returnNameOrGenericName($l["name"] ?? null, $l["genericName"] ?? null);
                            if ($n) {
                                $altNameList[] = $n;
                            }
                        }
                        if (!empty($altNameList)) {
                            $altNames = json_encode($altNameList);
                        }
                    }
                    
                    return self::createReturnArray($productName, $altNames);
                }
            }
            
            if ($result && isset($result["error"])) {
                API::logError("Custom Webhook error: " . $result["error"], false);
            }
        }

        return null;
    }
}
