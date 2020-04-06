<?php

/*
 * PHP-DB (https://github.com/delight-im/PHP-DB)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

namespace Delight\Db;

/** Individual measurement of a profiler that monitors performance */
interface Measurement {

	/**
	 * Returns the duration of the operation that was monitored
	 *
	 * @return float the duration in milliseconds
	 */
	public function getDuration();

	/**
	 * Returns the (parameterized) SQL query or statement that was used during the operation
	 *
	 * @return string
	 */
	public function getSql();

	/**
	 * Returns any values that might have been bound to the statement
	 *
	 * @return array|null
	 */
	public function getBoundValues();

	/**
	 * Returns the trace that shows the path taken through the application until the operation was executed
	 *
	 * @return array
	 */
	public function getTrace();

}
