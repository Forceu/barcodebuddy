<?php

/*
 * PHP-DB (https://github.com/delight-im/PHP-DB)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

namespace Delight\Db\Throwable;

/**
 * Error that is thrown when an empty `WHERE` clause is provided
 *
 * Although technically perfectly valid, an empty list of criteria is often provided by mistake
 *
 * This is why, for some operations, it is deemed too dangerous and thus disallowed
 *
 * Usually, one can simply execute a manual statement instead to get rid of this restriction
 */
class EmptyWhereClauseError extends Error {}
