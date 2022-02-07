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
 * Locale file. Setting correct locale to the user
 *
 * @author     Ole-Kenneth Bratholt
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */

$available_languages = [];
foreach(array_diff(scandir(__DIR__ . "/../locale", 1), ['..', '.']) as $lang) {
    $available_languages[substr(strtolower($lang), 0, 2)] = $lang;
}

$lang = strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en-US', 0, 2));
$locale = $available_languages[$lang] ?? 'en';

putenv("LC_ALL=$locale");
setlocale(LC_ALL, $locale);
bindtextdomain("messages", __DIR__ . "/../locale");
textdomain("messages");