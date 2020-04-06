<?php

/*
 * PHP-DB (https://github.com/delight-im/PHP-DB)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

namespace Delight\Db;

/** Implementation of a profiler that monitors performance of individual database queries and statements */
final class SimpleProfiler implements Profiler {

	/** The maximum number of entries in traces to use as the default */
	const TRACE_MAX_LENGTH_DEFAULT = 10;

	/** @var Measurement[] the measurements recorded by this instance */
	private $measurements;
	/** @var int the maximum number of entries in traces */
	private $maxTraceLength;
	/** @var float|null the start time of the current measurement in milliseconds */
	private $currentMeasurementStartTime;

	public function __construct($maxTraceLength = null) {
		$this->measurements = [];

		if ($maxTraceLength === null) {
			$this->maxTraceLength = self::TRACE_MAX_LENGTH_DEFAULT;
		}
		else {
			$this->maxTraceLength = (int) $maxTraceLength;
		}

		$this->currentMeasurementStartTime = null;
	}

	public function beginMeasurement() {
		$this->currentMeasurementStartTime = microtime(true) * 1000;
	}

	public function endMeasurement($sql, array $boundValues = null, $discardMostRecentTraceEntries = null) {
		if ($discardMostRecentTraceEntries === null) {
			$discardMostRecentTraceEntries = 0;
		}
		else {
			$discardMostRecentTraceEntries = (int) $discardMostRecentTraceEntries;
		}

		// get the trace at this point of the program execution
		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->maxTraceLength);

		// discard as many of the most recent entries as desired (but always discard at least the current method)
		for ($i = 0; $i < $discardMostRecentTraceEntries + 1; $i++) {
			array_shift($trace);
		}

		// calculate the duration in milliseconds
		$duration = (microtime(true) * 1000) - $this->currentMeasurementStartTime;

		// and finally record the measurement
		$this->measurements[] = new SimpleMeasurement(
			$duration,
			$sql,
			$boundValues,
			$trace
		);
	}

	public function getCount() {
		return count($this->measurements);
	}

	public function getMeasurement($index) {
		return $this->measurements[$index];
	}

	public function getMeasurements() {
		return $this->measurements;
	}

	public function sort() {
		usort($this->measurements, function ($a, $b) {
			/** @var Measurement $a */
			/** @var Measurement $b */
			return ($b->getDuration() - $a->getDuration());
		});
	}

}
