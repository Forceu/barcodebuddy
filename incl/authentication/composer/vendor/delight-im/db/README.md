# PHP-DB

Safe and convenient SQL database access in a driver-agnostic way

## Features

 * Convenient interface
 * Write less code for the same tasks
 * Safe against SQL injections through the use of prepared statements
 * Data returned by the server has the correct native types (i.e. no stringified fields)
 * Driver-agnostic support for multiple database systems

## Requirements

 * PHP 5.6.0+
   * PDO extension

## Installation

 1. Include the library via Composer [[?]](https://github.com/delight-im/Knowledge/blob/master/Composer%20(PHP).md):

    ```
    $ composer require delight-im/db
    ```

 1. Include the Composer autoloader:

    ```php
    require __DIR__ . '/vendor/autoload.php';
    ```

## Usage

### Connecting to a database

 * From a dynamic database configuration:

   ```php
   $dataSource = new \Delight\Db\PdoDataSource('mysql'); // see "Available drivers for database systems" below
   $dataSource->setHostname('localhost');
   $dataSource->setPort(3306);
   $dataSource->setDatabaseName('my-database');
   $dataSource->setCharset('utf8mb4');
   $dataSource->setUsername('my-username');
   $dataSource->setPassword('my-password');

   $db = \Delight\Db\PdoDatabase::fromDataSource($dataSource);
   ```

   This is the most convenient way to establish the database connection since the correct DSN will be created for you automatically. You can use the various setters with a fluent interface in order to set up your configuration.

   You can leave out any options that you don't need and check the `PdoDataSource` class for other available setters.

   Connections will be created lazily, i.e. they won't consume any resources before they're actually used. All that happens automatically.

 * From a DSN (data source name):

   ```php
   $db = \Delight\Db\PdoDatabase::fromDsn(
       new \Delight\Db\PdoDsn(
           'mysql:dbname=my-database;host=localhost',
           'my-username',
           'my-password'
       )
   );
   ```

   The parameters of the `PdoDsn` constructor are exactly the same as the parameters to the `PDO` constructor. But if you don't need another `PDO` instance, it's more efficient to go without `PDO` and create this library's instance from a DSN directly.

   Connections will be created lazily, i.e. they won't consume any resources before they're actually used.

 * Using an existing `PDO` instance:

   ```php
   // $pdo = new PDO('mysql:dbname=my-database;host=localhost;charset=utf8mb4', 'my-username', 'my-password');

   $db = \Delight\Db\PdoDatabase::fromPdo($pdo);
   ```

   This will not cause another database connection to be created but instead re-use the existing connection from the `PDO` instance that you provided.

   Furthermore, you can benefit from the strong guarantees about this library's behavior and from its convenience while still being able to use your existing `PDO` instance without any side effects.

   Just pass `true` as the second argument to the `fromPdo` method to completely preserve the original state of your `PDO` instance:

   ```php
   $db = \Delight\Db\PdoDatabase::fromPdo($pdo, true);
   ```

#### Available drivers for database systems

```php
\Delight\Db\PdoDataSource::DRIVER_NAME_MYSQL;
\Delight\Db\PdoDataSource::DRIVER_NAME_POSTGRESQL;
\Delight\Db\PdoDataSource::DRIVER_NAME_SQLITE;
```

### Selecting data

When selecting data, you get the desired results with just one method call. And, of course, you can easily choose whether you want to get multiple rows, a single row, a single value only, or a single column from all rows.

```php
$rows = $db->select('SELECT id, name FROM books');

// or

$rows = $db->select(
    'SELECT name, year FROM books WHERE author = ? ORDER BY copies DESC LIMIT 0, 10',
    [ 'Charles Dickens' ]
);

// or

$row = $db->selectRow(
    'SELECT author, year FROM books WHERE author <> ? ORDER BY year ASC LIMIT 0, 1',
    [ 'Miguel de Cervantes' ]
);

// or

$value = $db->selectValue(
    'SELECT year FROM books WHERE name <> ? AND name <> ? ORDER BY year DESC LIMIT 0, 1',
    [
        'Tale of Two Cities',
        'Alice in Wonderland'
    ]
);

// or

$column = $db->selectColumn(
    'SELECT author FROM books ORDER BY copies DESC LIMIT ?, ?',
    [
        0,
        3
    ]
);
```

### Inserting data

For simple insertions, you can use a convenient shorthand:

```php
$db->insert(
    'books',
    [
        // set
        'name' => 'Don Quixote',
        'author' => 'Miguel de Cervantes',
        'year' => 1612
    ]
);
```

Does your table have automatically generated primary IDs? Access to these inserted IDs is available with another short method call. The sequence name is only required for *some* database drivers, e.g. PostgreSQL.

```php
$newId = $db->getLastInsertId();
// or
$newId = $db->getLastInsertId('my-sequence-name');
```

If you need to execute more advanced statements, please refer to section "Executing statements" below.

### Updating data

For simple updates, you can use a convenient shorthand as well:

```php
$db->update(
    'books',
    [
        // set
        'author' => 'J. K. Rowling',
        'copies' => 2
    ],
    [
        // where
        'name' => "Harry Potter and the Philosopher's Stone"
    ]
);
```

Do you want to know how many rows have been updated by this operation? Just grab the return value from the `update` method call.

If you need to execute more advanced statements, please refer to section "Executing statements" below.

### Deleting data

Again, for simple deletions, you can use a convenient shorthand:

```php
$db->delete(
    'books',
    [
        // where
        'author' => 'C. S. Lewis',
        'year' => 1949
    ]
);
```

The return value from the `delete` method call will tell you how many rows have been deleted by this operation.

If you need to execute more advanced statements, please refer to section "Executing statements" below.

### Executing statements

You can execute any arbitrary SQL statements as shown in the following examples:

```php
$db->exec(
    'INSERT INTO books (name, author, year) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE copies = copies + 1',
    [
        "Harry Potter and the Philosopher's Stone",
        'J. K. Rowling',
        1997
    ]
);

// or

$db->exec(
    "UPDATE books SET name = CONCAT(LEFT(name, 5), ' in ', RIGHT(name, 10)) WHERE year >= ? AND year < ?",
    [
        1860,
        1890
    ]
);
```

For every statement that you execute, the return value will be the number of rows affected.

If the statement that you're executing is an `INSERT`, you can get the inserted IDs via the `getLastInsertId` method, again.

### Transactions

Transaction controls are as easy as they should be:

```php
$db->beginTransaction();

// and later

$db->commit();
// or
$db->rollBack();

// and optionally
$active = $db->isTransactionActive();
```

### Error handling

The methods from this library do not return any error codes. You will be informed about any problem via an exception.

### Input escaping

There's no need for you to escape any input manually. Just use the methods as shown above by using placeholders and passing the data separately. All input will be escaped for you automatically.

### Performance profiling

In order to monitor query performance during development, you can enable performance profiling:

```php
$db->setProfiler(new \Delight\Db\SimpleProfiler());
```

Whenever you want to see the analyzed queries or store them in a log file, you can get all measurements recorded by the profiler as an array:

```php
$db->getProfiler()->getMeasurements();
```

Typically, you'll want to sort the measurements before retrieving them, so that the longest-running queries are listed first:

```php
$db->getProfiler()->sort();
```

### Server and client information

In order to retrieve some information about the database server that you're connected to or about the database client used by PHP, you can use one of the following methods:

```php
$db->getDriverName();
// e.g. 'MySQL'

$db->getServerInfo();
// e.g. 'Uptime: 82196  Threads: 1  Questions: 2840  Slow queries: 0  Opens: 23  Flush tables: 1  Open tables: 33  Queries per second avg: 0.736'

$db->getServerVersion();
// e.g. '5.5.5-10.1.13-MariaDB'

$db->getClientVersion();
// e.g. 'mysqlnd 5.0.1-dev'
```

### Listeners

#### Connection established

```php
$db->addOnConnectListener(function (\Delight\Db\PdoDatabase $db) {
	// do something
});
```

## Contributing

All contributions are welcome! If you wish to contribute, please create an issue first so that your feature, problem or question can be discussed.

## License

This project is licensed under the terms of the [MIT License](https://opensource.org/licenses/MIT).
