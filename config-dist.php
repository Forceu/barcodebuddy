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
 *
 *
 * Config file for Barcode Buddy. These configurations cannot be changed
 * during runtime
 */


//Port for websocket server to use
const PORT_WEBSOCKET_SERVER        = 47631;

//Make sure to disallow reading the file in your webserver!
const DATABASE_PATH                = __DIR__ . '/../data/barcodebuddy.db';

//Set timeout for CURL
const CURL_TIMEOUT_S               = 20;


//If you are using a self-signed certificate on the Grocy server, enable this.
//WARNING: ONLY ENABLE IN THIS CASE! Potentially all data sent and received
//could be read or modified by a 3rd party!
const CURL_ALLOW_INSECURE_SSL_CA   = false;


//If the Grocy url does not match the one given in its SSL certificate, enable this.
//WARNING: ONLY ENABLE IN THIS CASE! Potentially all data sent and received
//could be read or modified by a 3rd party!
const CURL_ALLOW_INSECURE_SSL_HOST = false;

//Require API authentication for API
const REQUIRE_API_KEY              = true;

//Disable user authentication. Enable if you don't want to use username/password
const DISABLE_AUTHENTICATION       = false;

//Enable debug output
const IS_DEBUG                     = false;

//Enable to hide the Grocy link in the header
const HIDE_LINK_GROCY              = false;

//Enable to hide the Screen module link in the header
const HIDE_LINK_SCREEN             = false;

//Set your base grocy URL here if it differs from the API URL (eg. because of different network / DNS)
const EXTERNAL_GROCY_URL           = null;

//List of IPs and subnets that can bypass authentication. If using with a reverse proxy, ensure TRUSTED_PROXIES is set correctly.
const AUTHENTICATION_BYPASS_NETS   = array(
                                         // "192.168.0.1",
                                         // "127.0.0.1"
                                        );

// List of IPs and subnets that will be trusted for X-Forwarded-For header information
const TRUSTED_PROXIES              = array(
                                         // "192.168.0.1",
                                         // "127.0.0.1"
                                        );

//If you need to manually override a config value, you can do so with this array.
//Any overriden value cannot be changed through the UI anymore!
const OVERRIDDEN_USER_CONFIG       = array(
                                         //"BARCODE_C"                   => "BBUDDY-C",
                                         //"BARCODE_CS"                  => "BBUDDY-CS",
                                         //"BARCODE_P"                   => "BBUDDY-P",
                                         //"BARCODE_O"                   => "BBUDDY-O",
                                         //"BARCODE_GS"                  => "BBUDDY-I",
                                         //"BARCODE_Q"                   => "BBUDDY-Q-",
                                         //"BARCODE_AS"                  => "BBUDDY-AS",
                                         //"REVERT_TIME"                 => "10",
                                         //"REVERT_SINGLE"               => "1",
                                         //"MORE_VERBOSE"                => "1",
                                         //"GROCY_API_URL"               => null,
                                         //"GROCY_API_KEY"               => null,
                                         //"LAST_BARCODE"                => null,
                                         //"LAST_PRODUCT"                => null,
                                         //"WS_FULLSCREEN"               => "0",
                                         //"SHOPPINGLIST_REMOVE"         => "1",
                                         //"USE_GENERIC_NAME"            => "1",
                                         //"CONSUME_SAVED_QUANTITY"      => "0",
                                         //"DEFAULT_USE_GROCY_QU_FACTOR" => "0"
                                         );


// Currently not in use
const IS_DOCKER                    = false;

?>
