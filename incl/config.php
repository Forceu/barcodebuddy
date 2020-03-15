<?php

/**
 * Barcode Buddy for Grocy
 *
 * PHP version 7
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */

/**
 * Config file
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */

//Enable debug output
const IS_DEBUG      = false;


//Make sure to disallow reading the file in your webserver!
const DATABASE_PATH = __DIR__ . '/../data/barcodebuddy.db';

//Set timeout for CURL
const CURL_TIMEOUT_S  = 20;

//Enable debug as well if file "debug" exists in this directory
if (IS_DEBUG || file_exists(__DIR__ ."debug")) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

?>
