<?php

/*
 * PHP-DB (https://github.com/delight-im/PHP-DB)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

namespace Delight\Db;

/** Description of a data source for use with PHP's built-in PDO */
final class PdoDataSource implements DataSource {

	/** The driver name for MySQL */
	const DRIVER_NAME_MYSQL = 'mysql';
	/** The driver name for PostgreSQL */
	const DRIVER_NAME_POSTGRESQL = 'pgsql';
	/** The driver name for SQLite */
	const DRIVER_NAME_SQLITE = 'sqlite';
	/** The driver name for Oracle */
	const DRIVER_NAME_ORACLE = 'oci';
	/** Hostname for the virtual loopback interface */
	const HOST_LOOPBACK_NAME = 'localhost';
	/** IPv4 address for the virtual loopback interface */
	const HOST_LOOPBACK_IP = '127.0.0.1';
	/** The default hostname */
	const HOST_DEFAULT = self::HOST_LOOPBACK_NAME;

	/** @var string the name of the driver, e.g. `mysql` or `pgsql` */
	private $driverName;
	/** @var string|null the hostname where the database can be accessed, e.g. `db.example.com` */
	private $hostname;
	/** @var int|null the port number to use at the given host, e.g. `3306` or `5432` */
	private $port;
	/** @var string|null the UNIX socket to use, e.g. `/tmp/db.sock` */
	private $unixSocket;
	/** @var bool|null whether to keep the database in memory only */
	private $memory;
	/** @var string|null the path to the file where the database can be accessed on disk, e.g. `/opt/databases/mydb.ext` */
	private $filePath;
	/** @var string|null the name of the database, e.g. `my_application` */
	private $databaseName;
	/** @var string|null the character encoding of the database, e.g. `utf8` */
	private $charset;
	/** @var string|null the name of a user that can access the database */
	private $username;
	/** @var string|null the password corresponding to the username */
	private $password;

	/**
	 * Constructor
	 *
	 * @param string $driverName the name of the driver, e.g. `mysql` or `pgsql`
	 */
	public function __construct($driverName) {
		$this->driverName = (string) $driverName;
		$this->hostname = self::HOST_DEFAULT;
		$this->port = self::suggestPortFromDriverName($driverName);
		$this->unixSocket = null;
		$this->memory = null;
		$this->filePath = null;
		$this->databaseName = null;
		$this->charset = self::suggestCharsetFromDriverName($driverName);
		$this->username = null;
		$this->password = null;
	}

	/**
	 * Sets the hostname
	 *
	 * @param string|null $hostname the hostname where the database can be accessed, e.g. `db.example.com`
	 * @return static this instance for chaining
	 */
	public function setHostname($hostname = null) {
		$this->hostname = $hostname !== null ? (string) $hostname : null;

		return $this;
	}

	/**
	 * Sets the port number
	 *
	 * @param int|null $port the port number to use at the given host, e.g. `3306` or `5432`
	 * @return static this instance for chaining
	 */
	public function setPort($port = null) {
		$this->port = $port !== null ? (int) $port : null;

		return $this;
	}

	/**
	 * Sets the unix socket
	 *
	 * @param string|null $unixSocket the UNIX socket to use, e.g. `/tmp/db.sock`
	 * @return static this instance for chaining
	 */
	public function setUnixSocket($unixSocket = null) {
		$this->unixSocket = $unixSocket !== null ? (string) $unixSocket : null;

		return $this;
	}

	/**
	 * Sets whether to keep the database in memory only
	 *
	 * @param bool|null $memory whether to keep the database in memory only
	 * @return static this instance for chaining
	 */
	public function setMemory($memory = null) {
		$this->memory = $memory !== null ? (bool) $memory : null;

		return $this;
	}

	/**
	 * Sets the file path
	 *
	 * @param string|null $filePath the path to the file where the database can be accessed on disk, e.g. `/opt/databases/mydb.ext`
	 * @return static this instance for chaining
	 */
	public function setFilePath($filePath = null) {
		$this->filePath = $filePath !== null ? (string) $filePath : null;

		return $this;
	}

	/**
	 * Sets the database name
	 *
	 * @param string|null $databaseName the name of the database, e.g. `my_application`
	 * @return static this instance for chaining
	 */
	public function setDatabaseName($databaseName = null) {
		$this->databaseName = $databaseName !== null ? (string) $databaseName : null;

		return $this;
	}

	/**
	 * Sets the charset
	 *
	 * @param string|null $charset the character encoding, e.g. `utf8`
	 * @return static this instance for chaining
	 */
	public function setCharset($charset = null) {
		$this->charset = $charset !== null ? (string) $charset : null;

		return $this;
	}

	/**
	 * Sets the username
	 *
	 * @param string|null $username the name of a user that can access the database
	 * @return static this instance for chaining
	 */
	public function setUsername($username = null) {
		$this->username = $username;

		return $this;
	}

	/**
	 * Sets the password
	 *
	 * @param string|null $password the password corresponding to the username
	 * @return static this instance for chaining
	 */
	public function setPassword($password = null) {
		$this->password = $password;

		return $this;
	}

	public function toDsn() {
		$components = [];

		if (isset($this->hostname)) {
			if ($this->driverName !== self::DRIVER_NAME_ORACLE) {
				$hostname = $this->hostname;

				// if we're trying to connect to a local database
				if ($this->hostname === self::HOST_LOOPBACK_NAME) {
					// if we're using a non-standard port
					if (isset($this->port) && $this->port !== self::suggestPortFromDriverName($this->driverName)) {
						// force usage of TCP over UNIX sockets for the port change to take effect
						$hostname = self::HOST_LOOPBACK_IP;
					}
				}

				$components[] = 'host='.$hostname;
			}
		}

		if (isset($this->port)) {
			if ($this->driverName !== self::DRIVER_NAME_ORACLE) {
				$components[] = 'port='.$this->port;
			}
		}

		if (isset($this->unixSocket)) {
			$components[] = 'unix_socket='.$this->unixSocket;
		}

		if (isset($this->memory)) {
			if ($this->memory === true) {
				$components[] = ':memory:';
			}
		}

		if (isset($this->filePath)) {
			$components[] = $this->filePath;
		}

		if (isset($this->databaseName)) {
			if ($this->driverName === self::DRIVER_NAME_ORACLE) {
				$oracleLocation = [];

				if (isset($this->hostname)) {
					$oracleLocation[] = $this->hostname;
				}
				if (isset($this->port)) {
					$oracleLocation[] = $this->port;
				}

				if (count($oracleLocation) > 0) {
					$components[] = 'dbname=//'.implode(':', $oracleLocation).'/'.$this->databaseName;
				}
				else {
					$components[] = 'dbname='.$this->databaseName;
				}
			}
			else {
				$components[] = 'dbname='.$this->databaseName;
			}
		}

		if (isset($this->charset)) {
			if ($this->driverName === self::DRIVER_NAME_POSTGRESQL) {
				$components[] = 'client_encoding='.$this->charset;
			}
			else {
				$components[] = 'charset='.$this->charset;
			}
		}

		if (isset($this->username)) {
			$components[] = 'user='.$this->username;
		}

		if (isset($this->password)) {
			$components[] = 'password='.$this->password;
		}

		$dsnStr = $this->driverName . ':' . implode(';', $components);

		return new PdoDsn($dsnStr, $this->username, $this->password);
	}

	/**
	 * Suggests a default charset for a given driver
	 *
	 * @param string $driverName the name of the driver
	 * @return string|null the suggested charset
	 */
	private static function suggestCharsetFromDriverName($driverName) {
		switch ($driverName) {
			case self::DRIVER_NAME_MYSQL: return 'utf8mb4';
			case self::DRIVER_NAME_POSTGRESQL: return 'UTF8';
			default: return null;
		}
	}

	/**
	 * Suggests a default port number for a given driver
	 *
	 * @param string $driverName the name of the driver
	 * @return int|null the suggested port number
	 */
	private static function suggestPortFromDriverName($driverName) {
		switch ($driverName) {
			case self::DRIVER_NAME_MYSQL: return 3306;
			case self::DRIVER_NAME_POSTGRESQL: return 5432;
			default: return null;
		}
	}

}
