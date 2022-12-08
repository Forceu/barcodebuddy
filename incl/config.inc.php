<?php

include_once __DIR__ . "/processing.inc.php";

class BBConfig implements ArrayAccess, Iterator, Countable {
    /**
     * @var null|BBConfig
     */
    private static ?BBConfig $_BBConfigInstance = null;

    private $container = array();

    /**
     * BBConfig constructor.
     *
     * @param $db DatabaseConnection
     */
    private function __construct(DatabaseConnection $db) {
        global $CONFIG;

        $res = $db->getRawConfig();
        while ($row = $res->fetchArray()) {
            if (isset($CONFIG->OVERRIDDEN_USER_CONFIG[$row['data']]))
                $this->container[$row['data']] = $CONFIG->OVERRIDDEN_USER_CONFIG[$row['data']];
            else
                $this->container[$row['data']] = $row['value'];
        }
        if (sizeof($this->container) == 0) {
            die("DB Error: Could not get configuration");
        }

        if ($CONFIG->EXTERNAL_GROCY_URL != null)
            $this->container["GROCY_BASE_URL"] = $CONFIG->EXTERNAL_GROCY_URL;
        else
            $this->container["GROCY_BASE_URL"] = strrtrim($this->container["GROCY_API_URL"], "api/");

        if (!str_ends_with($this->container["GROCY_BASE_URL"], "/")) {
            $this->container["GROCY_BASE_URL"] .= "/";
        }
    }

    /**
     * Get an instance of DatabaseConnection
     * If an existing instance is available, it will be used.
     * If not available, and no instance is being created, a new connection will be established.
     * Otherwise, (such as during an ongoing upgrade in this php instance) an error will be thrown
     *
     * @param DatabaseConnection|null $db
     *
     * @return BBConfig
     * @throws DbConnectionDuringEstablishException
     */
    static function getInstance(DatabaseConnection $db = null): BBConfig {
        if (self::$_BBConfigInstance != null) {
            return self::$_BBConfigInstance;
        }

        self::$_BBConfigInstance = new BBConfig($db ? $db : DatabaseConnection::getInstance());
        return self::$_BBConfigInstance;
    }

    /**
     * Force config to update from the DB
     *
     * @return void
     */
    public static function forceRefresh(): void {
        self::$_BBConfigInstance = null;
    }

    /**
     * Set a key to the given value in the running copy of the DB-stored config
     *
     * @param null|string $offset Configuration Key
     *
     * @param string $value Configuration Value
     *
     * @deprecated N.B. If called directly, this config change will NOT be persisted in the database.
     */
    public function offsetSet($offset, $value): void {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }


    /**
     * Delete a given key from the DB-stored config
     *
     * @param string $offset
     *
     * * @deprecated N.B. If called directly, this config change will NOT be persisted in the database.
     */
    public function offsetUnset($offset): void {
        unset($this->container[$offset]);
    }


    /**
     * Check if a given key exists in the config
     *
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset): bool {
        return isset($this->container[$offset]);
    }


    /**
     * Get the value for a given key from the DB-stored config
     * @param string $offset
     *
     * @return string|null
     */
    public function offsetGet($offset): ?string {
        return $this->container[$offset] ?? null;
    }

    public function rewind(): void {
        reset($this->container);
    }

    #[ReturnTypeWillChange]
    public function current() {
        return current($this->container);
    }

    #[ReturnTypeWillChange]
    public function key() {
        return key($this->container);
    }

    #[ReturnTypeWillChange]
    public function next() {
        return next($this->container);
    }

    public function valid(): bool {
        return $this->current() !== false;
    }

    public function count(): int {
        return count($this->container);
    }

}
