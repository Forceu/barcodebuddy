<?php
/**
 * Barcode Buddy for Grocy
 *
 * PHP version 7
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 *
 * Helper file for curl
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.6
 */


class InvalidServerResponseException extends Exception {
}

class UnauthorizedException extends Exception {
}

class InvalidJsonResponseException extends Exception {
}

class InvalidSSLException extends Exception {
}

class InvalidParameterException extends Exception {
}

class NotFoundException extends Exception {
}

class LimitExceededException extends Exception {
}

class InternalServerErrorException extends Exception {
}


const METHOD_GET  = "GET";
const METHOD_PUT  = "PUT";
const METHOD_POST = "POST";

class CurlGenerator {
    private $ch;
    private $method;
    private $urlApi;
    private $ignoredResultCodes = array(400);

    const IGNORED_API_ERRORS_REGEX = array(
        '/No product with barcode .+ found/'
    );

    /**
     * CurlGenerator constructor.
     * @param string $url
     * @param string $method
     * @param string|null $jasonData
     * @param array|null $loginOverride
     * @param bool $noApiCall
     * @param array|null $ignoredResultCodes
     * @param array|null $formData
     * @param string|null $userAgent
     * @param array|null $headers
     * @throws DbConnectionDuringEstablishException
     */
    function __construct(string $url, string $method = METHOD_GET,
                         string $jasonData = null, array $loginOverride = null,
                         bool   $noApiCall = false, array $ignoredResultCodes = null,
                         array  $formData = null, string $userAgent = null,
                         array  $headers = null) {
        global $CONFIG;

        $config = BBConfig::getInstance();

        $this->method = $method;
        $this->urlApi = $url;
        $this->ch     = curl_init();
        if ($ignoredResultCodes != null)
            $this->ignoredResultCodes = $ignoredResultCodes;

        if ($loginOverride == null) {
            $apiKey = $config["GROCY_API_KEY"];
            $apiUrl = $config["GROCY_API_URL"];
        } else {
            $apiKey = $loginOverride[LOGIN_API_KEY];
            $apiUrl = $loginOverride[LOGIN_URL];
        }

        $headerArray = array();
        if (!$noApiCall)
            array_push($headerArray, 'GROCY-API-KEY: ' . $apiKey);

        if ($jasonData != null) {
            array_push($headerArray, 'Content-Type: application/json');
            array_push($headerArray, 'Content-Length: ' . strlen($jasonData));
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $jasonData);
        }

        if ($formData != null) {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $formData);
        }

        if ($headers != null) {
            foreach ($headers as $key => $value) {
                array_push($headerArray, $key . ": " . $value);
            }
        }

        if ($noApiCall)
            curl_setopt($this->ch, CURLOPT_URL, $url);
        else
            curl_setopt($this->ch, CURLOPT_URL, $apiUrl . $url);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headerArray);
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $this->method);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 5);
        if ($userAgent == null)
            curl_setopt($this->ch, CURLOPT_USERAGENT, 'BarcodeBuddy v' . BB_VERSION_READABLE);
        else
            curl_setopt($this->ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $CONFIG->CURL_TIMEOUT_S);
        if ($CONFIG->CURL_ALLOW_INSECURE_SSL_CA) {
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        if ($CONFIG->CURL_ALLOW_INSECURE_SSL_HOST) {
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
        }
    }

    /**
     * @param bool $decode
     * @return bool|mixed|string
     * @throws DbConnectionDuringEstablishException
     * @throws InternalServerErrorException
     * @throws InvalidJsonResponseException
     * @throws InvalidParameterException
     * @throws InvalidSSLException
     * @throws InvalidServerResponseException
     * @throws LimitExceededException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    function execute(bool $decode = false) {
        if (DISPLAY_DEBUG) {
            $startTime = microtime(true);
            DatabaseConnection::getInstance()->saveLog("<i>Executing API call: " . $this->urlApi . "</i>", false, false, true);
        }
        $curlResult = curl_exec($this->ch);
        $this->checkForErrorsAndThrow($curlResult);
        curl_close($this->ch);

        $jsonDecoded = json_decode($curlResult, true);
        if ($decode && isset($jsonDecoded->response->status) && $jsonDecoded->response->status == 'ERROR') {
            if (DISPLAY_DEBUG) {
                DatabaseConnection::getInstance()->saveLog($curlResult);
            }
            throw new InvalidJsonResponseException($jsonDecoded->response->errormessage);
        }

        if (isset($jsonDecoded["error_message"])) {
            $isIgnoredError = false;
            foreach (self::IGNORED_API_ERRORS_REGEX as $ignoredError) {
                if (preg_match($ignoredError, $jsonDecoded["error_message"]))
                    $isIgnoredError = true;
            }
            if (!$isIgnoredError) {
                if (DISPLAY_DEBUG) {
                    DatabaseConnection::getInstance()->saveLog($curlResult);
                }
                throw new InvalidJsonResponseException($jsonDecoded["error_message"]);
            }
        }
        if (DISPLAY_DEBUG) {
            $totalTimeMs = round((microtime(true) - $startTime) * 1000);
            DatabaseConnection::getInstance()->saveLog("<i>Executing took " . $totalTimeMs . "ms</i>", false, false, true);
        }
        if ($decode)
            return $jsonDecoded;
        else
            return $curlResult;
    }


    /**
     * @param bool|string $curlResult
     *
     * @return void
     * @throws InternalServerErrorException
     * @throws InvalidParameterException
     * @throws InvalidSSLException
     * @throws InvalidServerResponseException
     * @throws LimitExceededException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    private function checkForErrorsAndThrow($curlResult): void {
        $curlError    = curl_errno($this->ch);
        $responseCode = curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE);

        if (in_array($responseCode, $this->ignoredResultCodes))
            return;

        switch ($responseCode) {
            case 400:
                throw new InvalidParameterException();
            case 401:
                throw new UnauthorizedException();
            case 404:
                throw new NotFoundException();
            case 429:
                throw new LimitExceededException();
            case 500:
                throw new InternalServerErrorException();
        }
        if ($curlResult === false) {
            if (self::isErrorSslRelated($curlError))
                throw new InvalidSSLException();
            else
                throw new InvalidServerResponseException();
        } elseif ($curlResult == "" && $responseCode != 204) {
            throw new InvalidServerResponseException();
        }
    }

    private static function isErrorSslRelated(int $curlError): bool {
        return ($curlError == CURLE_SSL_CERTPROBLEM || $curlError == CURLE_SSL_CIPHER || $curlError == CURLE_SSL_CACERT);
    }
}
