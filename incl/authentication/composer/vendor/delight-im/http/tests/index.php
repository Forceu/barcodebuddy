<?php

/*
 * PHP-HTTP (https://github.com/delight-im/PHP-HTTP)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

// enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 'stdout');

// enable assertions
ini_set('assert.active', 1);
ini_set('zend.assertions', 1);
ini_set('assert.exception', 1);

header('Content-type: text/plain; charset=utf-8');

require __DIR__.'/../vendor/autoload.php';

define('TEST_HEADER_NAME', 'X-PHP-HTTP-Test');
define('TEST_HEADER_VALUE', 42);
define('TEST_HEADER', TEST_HEADER_NAME.': '.TEST_HEADER_VALUE);

\Delight\Http\ResponseHeader::set(TEST_HEADER_NAME, TEST_HEADER_VALUE);
assert(\Delight\Http\ResponseHeader::get(TEST_HEADER_NAME) === TEST_HEADER) or exit;
assert(\Delight\Http\ResponseHeader::get('Content-type') === 'Content-type: text/plain; charset=utf-8') or exit;
assert(\Delight\Http\ResponseHeader::get('Content-type', 'text/p') === 'Content-type: text/plain; charset=utf-8') or exit;
assert(\Delight\Http\ResponseHeader::get('Content-type', 'text/h') === null) or exit;

\Delight\Http\ResponseHeader::remove(TEST_HEADER_NAME, 'a');
assert(\Delight\Http\ResponseHeader::get(TEST_HEADER_NAME) === TEST_HEADER) or exit;

\Delight\Http\ResponseHeader::remove(TEST_HEADER_NAME, substr(TEST_HEADER_VALUE, 0, 4));
assert(\Delight\Http\ResponseHeader::get(TEST_HEADER_NAME) === null) or exit;

\Delight\Http\ResponseHeader::set(TEST_HEADER_NAME, TEST_HEADER_VALUE);
assert(\Delight\Http\ResponseHeader::get(TEST_HEADER_NAME) === TEST_HEADER) or exit;

assert(\Delight\Http\ResponseHeader::take(TEST_HEADER_NAME) === TEST_HEADER) or exit;
assert(\Delight\Http\ResponseHeader::get(TEST_HEADER_NAME) === null) or exit;

echo 'ALL TESTS PASSED'."\n";
