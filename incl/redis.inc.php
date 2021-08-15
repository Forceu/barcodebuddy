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
 * Redis cache connection
 *
 * @author     Marc Ole Bulling
 * @copyright  2020 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.6
 */


/**
 * Creates a cache connection and offers cache functions
 */


require_once __DIR__ . "/config.inc.php";

class RedisConnection {
    const KEY_CACHE_AVAILABLE               = "bbuddy_isavail";
    const KEY_CACHE_ALL_PRODUCT_INFO        = "bbuddy_apo";
    const KEY_CACHE_ALL_BARCODES            = "bbuddy_abc";
    const KEY_CACHE_NO_SOFT_UPDATE          = "bbuddy_softup";
    const TIMEOUT_REDIS_CACHE_S             = 5 * 60;
    const TIMEOUT_MAX_UPDATE_SOFT_REFRESH_S = 90;


    /**
     * Connects to Redis server
     * @return Redis|null
     * @throws RedisException Exception when unable to connect, for some reason not documented
     * @throws DbConnectionDuringEstablishException
     * @psalm-suppress PossiblyNullArgument
     */
    private static function establishConnection(): ?Redis {
        $config = BBConfig::getInstance();
        if (!$config["USE_REDIS"])
            return null;
        $redis       = new Redis();
        $isConnected = $redis->connect($config["REDIS_IP"], intval($config["REDIS_PORT"]), 0.2);
        if ($isConnected && $config["REDIS_PW"] != "") {
            $isConnected = $redis->auth($config["REDIS_PW"]);
        }
        if (!$isConnected)
            return null;
        return $redis;

    }

    private static function connectToRedis(): ?Redis {
        try {
            $redis = self::establishConnection();
        } catch (RedisException $e) {
            return null;
        }
        return $redis;
    }

    /**
     * Checks if Grocy data is cached
     * @return bool
     */
    public static function isCacheAvailable(): bool {
        return self::getData(self::KEY_CACHE_AVAILABLE) !== false;
    }

    /**
     * Gets a cached version of API::getAllProductsInfo()
     * @return array|null
     */
    public static function getAllProductsInfo(): ?array {
        $data = self::getData(self::KEY_CACHE_ALL_PRODUCT_INFO);
        if ($data === false)
            return null;
        $result = unserialize($data);
        if ($result === false)
            return null;
        return $result;
    }

    /**
     * Saves the result of API::getAllProductsInfo() to cache
     *
     * @param $input
     * @param GrocyProduct[] $input
     *
     * @return void
     */
    public static function cacheAllProductsInfo(array $input): void {
        self::setData(self::KEY_CACHE_AVAILABLE, "1");
        self::setData(self::KEY_CACHE_ALL_PRODUCT_INFO, serialize($input));
    }


    /**
     * Gets a cached version of API::getAllBarcodes()
     * @return array|null
     */
    public static function getAllBarcodes(): ?array {
        $data = self::getData(self::KEY_CACHE_ALL_BARCODES);
        if ($data === false)
            return null;
        $result = unserialize($data);
        if ($result === false)
            return null;
        return $result;
    }

    /**
     * Forces an update for all cache entries:
     * API::getAllProductsInfo()
     * API::getAllBarcodes()
     *
     * @param bool $softUpdate If true, do not update if less than 90 (TIMEOUT_MAX_UPDATE_SOFT_REFRESH_S) seconds have passed
     *
     * @return void
     */
    public static function updateCache(bool $softUpdate = false): void {
        if (self::isRedisAvailable()) {
            require_once __DIR__ . "/api.inc.php";
            if (!$softUpdate || self::isSoftUpdateAllowed()) {
                API::getAllProductsInfo(true);
                API::getAllBarcodes(true);
            }
        }
    }


    /**
     * Saves the result of API::getAllBarcodes() to cache
     *
     * @param $input
     * @param (mixed|string)[][] $input
     *
     * @return void
     */
    public static function cacheAllBarcodes(array $input): void {
        self::setData(self::KEY_CACHE_AVAILABLE, "1");
        self::setData(self::KEY_CACHE_ALL_BARCODES, serialize($input));
        self::setLimitSoftUpdate();
    }

    private static function setLimitSoftUpdate(): void {
        self::setData(self::KEY_CACHE_NO_SOFT_UPDATE, "1", self::TIMEOUT_MAX_UPDATE_SOFT_REFRESH_S);
    }

    private static function isSoftUpdateAllowed(): bool {
        return (self::getData(self::KEY_CACHE_NO_SOFT_UPDATE) === false);

    }

    public static function expireAllBarcodes(): void {
        self::expire(self::KEY_CACHE_ALL_BARCODES);
    }

    public static function expireAllProductInfo(): void {
        self::expire(self::KEY_CACHE_ALL_PRODUCT_INFO);
    }

    public static function invalidateCache(): void {
        self::expire(self::KEY_CACHE_AVAILABLE,
            self::KEY_CACHE_ALL_BARCODES,
            self::KEY_CACHE_ALL_PRODUCT_INFO
        );
    }

    private static function expire(string ...$keys): void {
        $redis = self::connectToRedis();
        if ($redis != null) {
            $redis->del($keys);
        }
    }

    private static function setData(string $key, string $data, int $timeout = self::TIMEOUT_REDIS_CACHE_S): void {
        $redis = self::connectToRedis();
        if ($redis != null) {
            $redis->set($key, $data, $timeout);
        }
    }

    /**
     * @return false|string
     */
    private static function getData(string $key) {
        $redis = self::connectToRedis();
        if ($redis != null) {
            return $redis->get($key);
        }
        return false;
    }

    public static function isRedisAvailable(): bool {
        return self::connectToRedis() != null;
    }

    /**
     * Checks if redis can be pinged.
     * @return bool true if successful
     * @throws Exception Errormessage if unsuccessful
     */
    public static function ping(): bool {
        $redis = self::connectToRedis();
        if ($redis != null) {
            $result = $redis->ping();
            if ($result === true)
                return true;
            else {
                throw new Exception($result);
            }
        }
        return false;
    }

    public static function getErrorMessage(): ?string {
        try {
            self::establishConnection();
        } catch (RedisException $e) {
            return $e->getMessage();
        }
        return null;
    }

}