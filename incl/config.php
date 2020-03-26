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


//Port for websocket server to use
const PORT_WEBSOCKET_SERVER        = 47631;

//Make sure to disallow reading the file in your webserver!
const DATABASE_PATH 		       = __DIR__ . '/../data/barcodebuddy.db';

//Set timeout for CURL
const CURL_TIMEOUT_S  		       = 20;


//If you are using a self-signed certificate on the Grocy server, enable this.
//WARNING: ONLY ENABLE IN THIS CASE! Potentially all data sent and received
//could be read or modified by a 3rd party!
const CURL_ALLOW_INSECURE_SSL_CA   = false;


//If the Grocy url does not match the one given in its SSL certificate, enable this.
//WARNING: ONLY ENABLE IN THIS CASE! Potentially all data sent and received
//could be read or modified by a 3rd party!
const CURL_ALLOW_INSECURE_SSL_HOST = false;


// If true, websockets cannot be disabled and internal port cannot be changed
const IS_DOCKER     		       = false;

//Enable debug output
const IS_DEBUG      		       = false;


//Enable debug as well if file "debug" exists in this directory
if (IS_DEBUG || file_exists(__DIR__ ."debug")) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}


const BB_VERSION          = "1411";
const BB_VERSION_READABLE = "1.4.1.1";

?>
