<?php

/*
 * PHP-DB (https://github.com/delight-im/PHP-DB)
 * Copyright (c) delight.im (https://www.delight.im/)
 * Licensed under the MIT License (https://opensource.org/licenses/MIT)
 */

namespace Delight\Db;

/** Description of a data source */
interface DataSource {

	/**
	 * Converts this instance to a DSN
	 *
	 * @return Dsn
	 */
	public function toDsn();

}
