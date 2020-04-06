<?php

/*
 * PHP-DB (https://github.com/delight-im/PHP-DB)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

// enable error reporting
\error_reporting(\E_ALL);
\ini_set('display_errors', 'stdout');

// enable assertions
\ini_set('assert.active', 1);
@\ini_set('zend.assertions', 1);
\ini_set('assert.exception', 1);

\header('Content-type: text/plain; charset=utf-8');

require __DIR__.'/../vendor/autoload.php';

$db = \Delight\Db\PdoDatabase::fromDsn(
	new \Delight\Db\PdoDsn(
		'mysql:dbname=my-database;host=localhost;charset=utf8mb4',
		'my-username',
		'my-password'
	)
);
