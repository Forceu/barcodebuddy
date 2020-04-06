<?php

/*
 * PHP-DB (https://github.com/delight-im/PHP-DB)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

namespace Delight\Db\Throwable;

/**
 * Exception that is thrown when an integrity constraint is being violated
 *
 * Common constraints include 'UNIQUE', 'NOT NULL' and 'FOREIGN KEY'
 *
 * Ambiguous column references constitute violations as well
 */
class IntegrityConstraintViolationException extends Exception {}
