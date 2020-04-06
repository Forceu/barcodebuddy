<?php

/*
 * PHP-DB (https://github.com/delight-im/PHP-DB)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

namespace Delight\Db;

/** Profiler that monitors performance of individual database queries and statements */
interface Profiler {

	/** Starts a new measurement */
	public function beginMeasurement();

	/**
	 * Ends a previously started measurement
	 *
	 * @param string $sql the SQL query or statement that was monitored
	 * @param array|null $boundValues (optional) the values that have been bound to the query or statement
	 * @param int|null $discardMostRecentTraceEntries (optional) the number of trace entries that should be discarded (starting with the most recent ones)
	 */
	public function endMeasurement($sql, array $boundValues = null, $discardMostRecentTraceEntries = null);

	/**
	 * Returns the number of measurements that this profiler has recorded
	 *
	 * @return int
	 */
	public function getCount();

	/**
	 * Returns the measurement at the specified index
	 *
	 * @param int $index the index of the measurement to return
	 * @return Measurement
	 */
	public function getMeasurement($index);

	/**
	 * Returns all measurements that this profiler has recorded
	 *
	 * @return Measurement[]
	 */
	public function getMeasurements();

	/** Sorts the measurements that this profiler has recorded so that the longest-running operations are listed first */
	public function sort();

}
