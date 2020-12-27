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
class RedisConnection {

    private static function connectToRedis(): ?Redis {
        $redis       = new Redis();
        $isConnected = $redis->connect('127.0.0.1', 6379, 0.2);
        if (!$isConnected)
            return null;
        return $redis;
    }

    public static function setData($key, $data) {
        $redis = self::connectToRedis();
        if ($redis != null) {
            $redis->set($key, $data);
        }
    }

    public static function getData($key) {
        $redis = self::connectToRedis();
        if ($redis != null) {
            return $redis->get($key);
        }
    }

    public static function isRedisAvailable(): bool {
        return self::connectToRedis() != null;
    }

}