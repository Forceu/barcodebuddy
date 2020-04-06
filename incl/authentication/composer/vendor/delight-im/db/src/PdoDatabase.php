<?php

/*
 * PHP-DB (https://github.com/delight-im/PHP-DB)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

namespace Delight\Db;

use PDO;
use PDOException;
use PDOStatement;
use Delight\Db\Throwable\BeginTransactionFailureException;
use Delight\Db\Throwable\CommitTransactionFailureException;
use Delight\Db\Throwable\EmptyValueListError;
use Delight\Db\Throwable\EmptyWhereClauseError;
use Delight\Db\Throwable\RollBackTransactionFailureException;

/** Database access using PHP's built-in PDO */
final class PdoDatabase implements Database {

	/** @var array|null the old connection attributes to restore during denormalization */
	private $previousAttributes;
	/** @var array|null the new connection attributes to apply during normalization */
	private $attributes;
	/** @var PDO|null the connection that this class operates on (may be lazily loaded) */
	private $pdo;
	/** @var PdoDsn|null the PDO-specific DSN that may be used to establish the connection */
	private $dsn;
	/** @var string|null the name of the driver that is used for the current connection (may be lazily loaded) */
	private $driverName;
	/** @var Profiler|null the profiler that is used to analyze query performance during development */
	private $profiler;
	/** @var callable[] the list of pending callbacks to execute when the connection has been established */
	private $onConnectListeners;

	/**
	 * Constructor
	 *
	 * This is private to prevent direct usage
	 *
	 * Call one of the static factory methods instead
	 *
	 * @param PDO|null $pdoInstance (optional) the connection that this class operates on
	 * @param PdoDsn|null $pdoDsn (optional) the PDO-specific DSN that may be used to establish the connection
	 * @param bool|null (optional) $preserveOldState whether the old state of the connection should be preserved
	 */
	private function __construct(PDO $pdoInstance = null, PdoDsn $pdoDsn = null, $preserveOldState = null) {
		// if the old state of the connection must be stored somewhere
		if ($preserveOldState) {
			// prepare an array for that task
			$this->previousAttributes = [];
		}
		// if the old state of the connection doesn't need to be tracked
		else {
			$this->previousAttributes = null;
		}

		// track the new attributes that should be applied during normalization
		$this->attributes = [
			// set the error mode for this connection to throw exceptions
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			// set the default fetch mode for this connection to use associative arrays
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			// prefer native prepared statements over emulated ones
			PDO::ATTR_EMULATE_PREPARES => false,
			// use lowercase and uppercase as returned by the server
			PDO::ATTR_CASE => PDO::CASE_NATURAL,
			// don't convert numeric values to strings when fetching data
			PDO::ATTR_STRINGIFY_FETCHES => false,
			// keep `null` values and empty strings as returned by the server
			PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL
		];

		$this->pdo = $pdoInstance;
		$this->dsn = $pdoDsn;
		$this->profiler = null;
		$this->onConnectListeners = [];
	}

	/**
	 * Creates and returns a new instance from an existing PDO instance
	 *
	 * @param PDO $pdoInstance the PDO instance to use
	 * @param bool|null (optional) $preserveOldState whether the old state of the PDO instance should be preserved
	 * @return static the new instance
	 */
	public static function fromPdo(PDO $pdoInstance, $preserveOldState = null) {
		return new static($pdoInstance, null, $preserveOldState);
	}

	/**
	 * Creates and returns a new instance from a PDO-specific DSN
	 *
	 * The connection will be lazily loaded, i.e. it won't be established before it's actually needed
	 *
	 * @param PdoDsn $pdoDsn the PDO-specific DSN to use
	 * @return static the new instance
	 */
	public static function fromDsn(PdoDsn $pdoDsn) {
		return new static(null, $pdoDsn);
	}

	/**
	 * Creates and returns a new instance from a data source described for use with PDO
	 *
	 * The connection will be lazily loaded, i.e. it won't be established before it's actually needed
	 *
	 * @param PdoDataSource $pdoDataSource the data source to use
	 * @return static the new instance
	 */
	public static function fromDataSource(PdoDataSource $pdoDataSource) {
		return new static(null, $pdoDataSource->toDsn());
	}

	public function select($query, array $bindValues = null) {
		return $this->selectInternal(function ($stmt) {
			/** @var PDOStatement $stmt */
			return $stmt->fetchAll();
		}, $query, $bindValues);
	}

	public function selectValue($query, array $bindValues = null) {
		return $this->selectInternal(function ($stmt) {
			/** @var PDOStatement $stmt */
			return $stmt->fetchColumn(0);
		}, $query, $bindValues);
	}

	public function selectRow($query, array $bindValues = null) {
		return $this->selectInternal(function ($stmt) {
			/** @var PDOStatement $stmt */
			return $stmt->fetch();
		}, $query, $bindValues);
	}

	public function selectColumn($query, array $bindValues = null) {
		return $this->selectInternal(function ($stmt) {
			/** @var PDOStatement $stmt */
			return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
		}, $query, $bindValues);
	}

	public function insert($tableName, array $insertMappings) {
		// if no values have been provided that could be inserted
		if (empty($insertMappings)) {
			// we cannot perform an insert here
			throw new EmptyValueListError();
		}

		// escape the table name
		$tableName = $this->quoteTableName($tableName);
		// get the column names
		$columnNames = array_keys($insertMappings);
		// escape the column names
		$columnNames = array_map([ $this, 'quoteIdentifier' ], $columnNames);
		// build the column list
		$columnList = implode(', ', $columnNames);
		// prepare the values (which are placeholders only)
		$values = array_fill(0, count($insertMappings), '?');
		// build the value list
		$placeholderList = implode(', ', $values);
		// and finally build the full statement (still using placeholders)
		$statement = 'INSERT INTO '.$tableName.' ('.$columnList.') VALUES ('.$placeholderList.');';

		// execute the (parameterized) statement and supply the values to be bound to it
		return $this->exec($statement, array_values($insertMappings));
	}

	public function update($tableName, array $updateMappings, array $whereMappings) {
		// if no values have been provided that we could update to
		if (empty($updateMappings)) {
			// we cannot perform an update here
			throw new EmptyValueListError();
		}

		// if no values have been provided that we could filter by (which is possible but dangerous)
		if (empty($whereMappings)) {
			// we should not perform an update here
			throw new EmptyWhereClauseError();
		}

		// escape the table name
		$tableName = $this->quoteTableName($tableName);
		// prepare a list for the values to be bound (both by the list of new values and by the conditions)
		$bindValues = [];
		// prepare a list for the individual directives of the `SET` clause
		$setDirectives = [];

		// for each mapping of a column name to its respective new value
		foreach ($updateMappings as $updateColumn => $updateValue) {
			// create an individual directive with the column name and a placeholder for the value
			$setDirectives[] = $this->quoteIdentifier($updateColumn) . ' = ?';
			// and remember which value to bind here
			$bindValues[] = $updateValue;
		}

		// prepare a list for the individual predicates of the `WHERE` clause
		$wherePredicates = [];

		// for each mapping of a column name to its respective value to filter by
		foreach ($whereMappings as $whereColumn => $whereValue) {
			// create an individual predicate with the column name and a placeholder for the value
			$wherePredicates[] = $this->quoteIdentifier($whereColumn) . ' = ?';
			// and remember which value to bind here
			$bindValues[] = $whereValue;
		}

		// build the full statement (still using placeholders)
		$statement = 'UPDATE '.$tableName.' SET '.implode(', ', $setDirectives).' WHERE '.implode(' AND ', $wherePredicates).';';

		// execute the (parameterized) statement and supply the values to be bound to it
		return $this->exec($statement, $bindValues);
	}

	public function delete($tableName, array $whereMappings) {
		// if no values have been provided that we could filter by (which is possible but dangerous)
		if (empty($whereMappings)) {
			// we should not perform a deletion here
			throw new EmptyWhereClauseError();
		}

		// escape the table name
		$tableName = $this->quoteTableName($tableName);
		// prepare a list for the values to be bound by the conditions
		$bindValues = [];
		// prepare a list for the individual predicates of the `WHERE` clause
		$wherePredicates = [];

		// for each mapping of a column name to its respective value to filter by
		foreach ($whereMappings as $whereColumn => $whereValue) {
			// create an individual predicate with the column name and a placeholder for the value
			$wherePredicates[] = $this->quoteIdentifier($whereColumn) . ' = ?';
			// and remember which value to bind here
			$bindValues[] = $whereValue;
		}

		// build the full statement (still using placeholders)
		$statement = 'DELETE FROM '.$tableName.' WHERE '.implode(' AND ', $wherePredicates).';';

		// execute the (parameterized) statement and supply the values to be bound to it
		return $this->exec($statement, $bindValues);
	}

	public function exec($statement, array $bindValues = null) {
		$this->normalizeConnection();

		try {
			// create a prepared statement from the supplied SQL string
			$stmt = $this->pdo->prepare($statement);
		}
		catch (PDOException $e) {
			ErrorHandler::rethrow($e);
		}

		// if a performance profiler has been defined
		if (isset($this->profiler)) {
			$this->profiler->beginMeasurement();
		}

		/** @var PDOStatement $stmt */

		try {
			// bind the supplied values to the statement and execute it
			$stmt->execute($bindValues);
		}
		catch (PDOException $e) {
			ErrorHandler::rethrow($e);
		}

		// if a performance profiler has been defined
		if (isset($this->profiler)) {
			$this->profiler->endMeasurement($statement, $bindValues);
		}

		// get the number of rows affected by this operation
		$affectedRows = $stmt->rowCount();

		$this->denormalizeConnection();

		return $affectedRows;
	}

	public function getLastInsertId($sequenceName = null) {
		$this->normalizeConnection();

		$id = $this->pdo->lastInsertId($sequenceName);

		$this->denormalizeConnection();

		return $id;
	}

	public function beginTransaction() {
		$this->normalizeConnection();

		try {
			$success = $this->pdo->beginTransaction();
		}
		catch (PDOException $e) {
			$success = $e->getMessage();
		}

		$this->denormalizeConnection();

		if ($success !== true) {
			throw new BeginTransactionFailureException(is_string($success) ? $success : null);
		}
	}

	public function startTransaction() {
		$this->beginTransaction();
	}

	public function isTransactionActive() {
		$this->normalizeConnection();

		$state = $this->pdo->inTransaction();

		$this->denormalizeConnection();

		return $state;
	}

	public function commit() {
		$this->normalizeConnection();

		try {
			$success = $this->pdo->commit();
		}
		catch (PDOException $e) {
			$success = $e->getMessage();
		}

		$this->denormalizeConnection();

		if ($success !== true) {
			throw new CommitTransactionFailureException(is_string($success) ? $success : null);
		}
	}

	public function rollBack() {
		$this->normalizeConnection();

		try {
			$success = $this->pdo->rollBack();
		}
		catch (PDOException $e) {
			$success = $e->getMessage();
		}

		$this->denormalizeConnection();

		if ($success !== true) {
			throw new RollBackTransactionFailureException(is_string($success) ? $success : null);
		}
	}

	public function getProfiler() {
		return $this->profiler;
	}

	public function setProfiler(Profiler $profiler = null) {
		$this->profiler = $profiler;

		return $this;
	}

	public function getDriverName() {
		$this->ensureConnected();

		switch ($this->driverName) {
			case PdoDataSource::DRIVER_NAME_MYSQL: return 'MySQL';
			case PdoDataSource::DRIVER_NAME_POSTGRESQL: return 'PostgreSQL';
			case PdoDataSource::DRIVER_NAME_SQLITE: return 'SQLite';
			case PdoDataSource::DRIVER_NAME_ORACLE: return 'Oracle';
			default: return $this->driverName;
		}
	}

	/**
	 * Returns some driver-specific information about the server that this instance is connected to
	 *
	 * @return string
	 */
	public function getServerInfo() {
		$this->ensureConnected();

		return $this->pdo->getAttribute(PDO::ATTR_SERVER_INFO);
	}

	/**
	 * Returns the version of the database software used on the server that this instance is connected to
	 *
	 * @return string
	 */
	public function getServerVersion() {
		$this->ensureConnected();

		return $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
	}

	/**
	 * Returns the version of the database client used by this instance
	 *
	 * @return string
	 */
	public function getClientVersion() {
		$this->ensureConnected();

		return $this->pdo->getAttribute(PDO::ATTR_CLIENT_VERSION);
	}

	public function quoteIdentifier($identifier) {
		$this->ensureConnected();

		if ($this->driverName === PdoDataSource::DRIVER_NAME_MYSQL) {
			$char = '`';
		}
		else {
			$char = '"';
		}

		return $char . str_replace($char, $char . $char, $identifier) . $char;
	}

	public function quoteTableName($tableName) {
		if (\is_array($tableName)) {
			$tableName = \array_map([ $this, 'quoteIdentifier' ], $tableName);

			return \implode('.', $tableName);
		}
		else {
			return $this->quoteIdentifier($tableName);
		}
	}

	public function quoteLiteral($literal) {
		$this->ensureConnected();

		if (!empty($literal) && is_array($literal)) {
			foreach ($literal as $key => $value) {
				$literal[$key] = $this->pdo->quote($value);
			}

			return $literal;
		}
		else {
			return $this->pdo->quote($literal);
		}
	}

	public function addOnConnectListener(callable $onConnectListener) {
		// if the database connection has not been established yet
		if ($this->pdo === null) {
			// schedule the callback for later execution
			$this->onConnectListeners[] = $onConnectListener;
		}
		// if the database connection has already been established
		else {
			// execute the callback immediately
			$onConnectListener($this);
		}

		return $this;
	}

	/** Makes sure that the connection is active and otherwise establishes it automatically */
	private function ensureConnected() {
		if ($this->pdo === null) {
			try {
				$this->pdo = new PDO($this->dsn->getDsn(), $this->dsn->getUsername(), $this->dsn->getPassword());
			}
			catch (PDOException $e) {
				ErrorHandler::rethrow($e);
			}

			// iterate over all listeners waiting for the connection to be established
			foreach ($this->onConnectListeners as $onConnectListener) {
				// execute the callback
				$onConnectListener($this);
			}

			// discard the listeners now that they have all been executed
			$this->onConnectListeners = [];

			$this->dsn = null;
		}

		if ($this->driverName === null) {
			$this->driverName = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
		}
	}

	/** Normalizes this connection by setting attributes that provide the strong guarantees about the connection's behavior that we need */
	private function normalizeConnection() {
		$this->ensureConnected();

		$this->configureConnection($this->attributes, $this->previousAttributes);
	}

	/** Restores this connection's original behavior if desired */
	private function denormalizeConnection() {
		$this->configureConnection($this->previousAttributes, $this->attributes);
	}

	/**
	 * Configures this connection by setting appropriate attributes
	 *
	 * @param array|null $newAttributes the new attributes to set
	 * @param array|null $oldAttributes where old configurations may be saved to restore them later
	 */
	private function configureConnection(array &$newAttributes = null, array &$oldAttributes = null) {
		// if a connection is available
		if (isset($this->pdo)) {
			// if there are attributes that need to be applied
			if (isset($newAttributes)) {
				// get the keys and values of the attributes to apply
				foreach ($newAttributes as $key => $newValue) {
					// if the old state of the connection must be preserved
					if (isset($oldAttributes)) {
						// retrieve the old value for this attribute
						try {
							$oldValue = @$this->pdo->getAttribute($key);
						}
						catch (PDOException $e) {
							// the specified attribute is not supported by the driver
							$oldValue = null;
						}

						// if an old value has been found
						if (isset($oldValue)) {
							// if the old value differs from the new value that we're going to set
							if ($oldValue !== $newValue) {
								// save the old value so that we're able to restore it later
								$oldAttributes[$key] = $oldValue;
							}
						}
					}

					// and then set the desired new value
					$this->pdo->setAttribute($key, $newValue);
				}

				// if the old state of the connection doesn't need to be preserved
				if (!isset($oldAttributes)) {
					// we're done updating attributes for this connection once and for all
					$newAttributes = null;
				}
			}
		}
	}

	/**
	 * Selects from the database using the specified query and returns what the supplied callback extracts from the result set
	 *
	 * You should not include any dynamic input in the query
	 *
	 * Instead, pass `?` characters (without any quotes) as placeholders and pass the actual values in the third argument
	 *
	 * @param callable $callback the callback that receives the executed statement and can then extract and return the desired results
	 * @param string $query the query to select with
	 * @param array|null $bindValues (optional) the values to bind as replacements for the `?` characters in the query
	 * @return mixed whatever the callback has extracted and returned from the result set
	 */
	private function selectInternal(callable $callback, $query, array $bindValues = null) {
		$this->normalizeConnection();

		try {
			// create a prepared statement from the supplied SQL string
			$stmt = $this->pdo->prepare($query);
		}
		catch (PDOException $e) {
			ErrorHandler::rethrow($e);
		}

		// if a performance profiler has been defined
		if (isset($this->profiler)) {
			$this->profiler->beginMeasurement();
		}

		/** @var PDOStatement $stmt */

		// bind the supplied values to the query and execute it
		try {
			$stmt->execute($bindValues);
		}
		catch (PDOException $e) {
			ErrorHandler::rethrow($e);
		}

		// if a performance profiler has been defined
		if (isset($this->profiler)) {
			$this->profiler->endMeasurement($query, $bindValues, 1);
		}

		// fetch the desired results from the result set via the supplied callback
		$results = $callback($stmt);

		$this->denormalizeConnection();

		// if the result is empty
		if (empty($results) && $stmt->rowCount() === 0 && ($this->driverName !== PdoDataSource::DRIVER_NAME_SQLITE || \is_bool($results) || \is_array($results))) {
			// consistently return `null`
			return null;
		}
		// if some results have been found
		else {
			// return these as extracted by the callback
			return $results;
		}
	}

}
