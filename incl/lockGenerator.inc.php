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
 * Helper to prevent race conditions
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.5
 */


class LockGenerator {
	private $file = null;
	private $isLocked = false;

	function createLock(): void {
		$this->file     = fopen(__DIR__ . '/lockGenerator.inc.php', "r");
		$this->isLocked = true;
		flock($this->file, LOCK_EX);
	}

	function removeLock(): void {
		if ($this->file == null)
			throw new Exception("Lock has not been created!");
		if ($this->isLocked) {
			flock($this->file, LOCK_UN);
			fclose($this->file);
		}
		$this->isLocked = false;
	}
}
