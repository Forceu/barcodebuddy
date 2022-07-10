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
 * @since      File available since Release 1.2
 */

/**
 * Functions for internal checking file
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.2
 */


require_once __DIR__ . "/configProcessing.inc.php";
require_once __DIR__ . "/config.inc.php";

const RESULT_ALL_INSTALLED = 0;
const RESULT_OPT_MISSING   = 1;
const RESULT_REQ_MISSING   = 2;

const REQ_EXTENSIONS      = array("curl", "date", "sqlite3", "json", "sockets", "redis", "mbstring", "gettext");
const OPTIONAL_EXTENSIONS = array();


/**
 * Checks if the required PHP extensions are installed and active
 *
 * @return array  Returns an array for all modules. $return["result"] is
 *                 RESULT_ALL_INSTALLED, RESULT_OPT_MISSING or RESULT_REQ_MISSING
 */
function checkExtensionsInstalled(): array {
    $loadedExtensions = get_loaded_extensions();
    $result           = array();
    $result["req"]    = array();
    $result["opt"]    = array();
    $result["result"] = RESULT_ALL_INSTALLED;
    foreach (REQ_EXTENSIONS as $module) {
        $isLoaded               = in_array($module, $loadedExtensions);
        $result["req"][$module] = $isLoaded;
        if (!$isLoaded) {
            $result["result"] = RESULT_REQ_MISSING;
        }
    }
    foreach (OPTIONAL_EXTENSIONS as $module) {
        $isLoaded               = in_array($module, $loadedExtensions);
        $result["opt"][$module] = $isLoaded;
        if (!$isLoaded && $result["result"] != RESULT_REQ_MISSING) {
            $result["result"] = RESULT_OPT_MISSING;
        }
    }
    return $result;
}


/**
 * Check a specific extension
 * @param string $name
 * @return bool
 */
function isExtensionInstalled(string $name): bool {
    $loadedExtensions = get_loaded_extensions();
    return in_array($name, $loadedExtensions);
}

/**
 * Check if Grocy API config is set in the database
 * @return bool
 * @throws DbConnectionDuringEstablishException
 */
function isGrocyApiSet(): bool {
    $config = BBConfig::getInstance();
    return ($config["GROCY_API_URL"] != null && $config["GROCY_API_KEY"] != null);
}
