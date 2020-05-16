<?php


class BBConfig implements ArrayAccess
{
    private static $_BBConfigInstance = null;

    private $container = array();

    /**
     * BBConfig constructor.
     *
     * @param $db DatabaseConnection
     */
    private function __construct($db)
    {
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
        $this->container["GROCY_BASE_URL"] = strrtrim($this->container["GROCY_API_URL"], "api/");
    }

    /**
     * Get an instance of DatabaseConnection
     * If an existing instance is available, it will be used.
     * If not available, and no instance is being created, a new connection will be established.
     * Otherwise (such as during an ongoing upgrade in this php instance) an error will be thrown
     *
     * @param null $db
     *
     * @return BBConfig
     */
    static function getInstance($db = null) {
        if(self::$_BBConfigInstance != null) {
            return self::$_BBConfigInstance;
        }

        self::$_BBConfigInstance = new BBConfig($db ? $db : DatabaseConnection::getInstance());
        return self::$_BBConfigInstance;
    }

    /**
     * Force config to update from the DB
     */
    public static function forceRefresh()
    {
        self::$_BBConfigInstance = null;
    }

    /**
     * Set a key to the given value in the running copy of the DB-stored config
     *
     * @param string $offset Configuration Key
     *
     * @param string $value Configuration Value
     *
     * @deprecated N.B. If called directly, this config change will NOT be persisted in the database.
     */
    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * Check if a given key exists in the config
     *
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset) {
        return isset($this->container[$offset]);
    }

    /**
     * Delete a given key from the DB-stored config
     *
     * @param string $offset
     *
     * * @deprecated N.B. If called directly, this config change will NOT be persisted in the database.
     */
    public function offsetUnset($offset) {
        unset($this->container[$offset]);
    }

    /**
     * Get the value for a given key from the DB-stored config
     * @param string $offset
     *
     * @return string|null
     */
    public function offsetGet($offset) {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }
}