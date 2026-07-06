# mysqldump-php

[![Run tests](https://github.com/druidfi/mysqldump-php/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/druidfi/mysqldump-php/actions/workflows/tests.yml)
[![Total Downloads](https://poser.pugx.org/druidfi/mysqldump-php/downloads)](https://packagist.org/packages/druidfi/mysqldump-php)
[![Monthly Downloads](https://poser.pugx.org/druidfi/mysqldump-php/d/monthly)](https://packagist.org/packages/druidfi/mysqldump-php)
[![Daily Downloads](https://poser.pugx.org/druidfi/mysqldump-php/d/daily)](https://packagist.org/packages/druidfi/mysqldump-php)
[![Latest Stable Version](https://poser.pugx.org/druidfi/mysqldump-php/v/stable.png)](https://packagist.org/packages/druidfi/mysqldump-php)

This is a PHP version of `mysqldump` cli that comes with MySQL. It can be used for interacting with the data before
creating the database dump. E.g. it can modify the contents of tables and is thus good for anonymize data.

Out of the box, `mysqldump-php` supports backing up table structures, the data itself, views, triggers and events.

`mysqldump-php` supports:

- output binary blobs as hex
- resolves view dependencies (using Stand-In tables)
- output compared against original mysqldump
- dumps stored routines (functions and procedures)
- dumps events
- does extended-insert and/or complete-insert
- supports virtual columns from MySQL 5.7
- does insert-ignore, like a REPLACE but ignoring errors if a duplicate key exists
- modifying data from database on-the-fly when dumping, using hooks
- can save directly to Google Cloud storage over a compressed stream wrapper (GZIPSTREAM)

## Requirements

- PHP 8.4 or newer with PDO - [see supported versions](https://www.php.net/supported-versions.php)
- MySQL 8.0 or newer (and compatible MariaDB)

## Versions

| Version | Branch | PHP       | Status | Tests |
|---------|--------|-----------|--------|-------|
| 3.x     | `main` | 8.4+      | In development | [![Run tests](https://github.com/druidfi/mysqldump-php/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/druidfi/mysqldump-php/actions/workflows/tests.yml?query=branch%3Amain) |
| 2.x     | `2.x`  | 8.1+      | Maintenance | [![Run tests](https://github.com/druidfi/mysqldump-php/actions/workflows/tests.yml/badge.svg?branch=2.x)](https://github.com/druidfi/mysqldump-php/actions/workflows/tests.yml?query=branch%3A2.x) |
| 1.x     | `1.x`  | 7.4 / 8.0 | Legacy | |

## Upgrading from 2.x to 3.x

The dump output and the day-to-day API — constructor, `start()`, the transform/info hooks,
`setTableWheres()`/`setTableLimits()` and the dump settings with their defaults — are unchanged
from 2.x. The following changes may require action:

- **PHP 8.4 or newer is required** (2.x supports PHP 8.1+).
- **Connections are no longer persistent by default.** 2.x always set
  `PDO::ATTR_PERSISTENT => true`. If you relied on persistent connections, pass
  `PDO::ATTR_PERSISTENT => true` in the `$pdoOptions` constructor argument.
- **`Mysqldump::addTypeAdapter()` no longer affects other instances.** In 2.x the adapter class
  was stored statically and leaked into every `Mysqldump` instance in the same process. Call
  `addTypeAdapter()` on each instance that needs a custom adapter.
- **`CompressManagerFactory::$methods` was removed.** Use the `CompressMethod` enum, or the
  unchanged class constants such as `CompressManagerFactory::GZIP`.
- **The `Druidfi\Mysqldump\Attribute\Deprecated` attribute class was removed.** Deprecations now
  use the native PHP 8.4 `#[\Deprecated]` attribute.
- **`ConfigValidator::checkDeprecated()` return value changed.** The `reason` and `alternative`
  keys were replaced by a single `message` key (`deprecated` and `since` are unchanged).
- **`DumpSettings::get()` returns the setting's actual type.** In 2.x every value was cast to
  `string`; now arrays, booleans and integers come back unchanged, and unknown options return
  `null` instead of the string `""`. Prefer the typed getters (`getWhere()`,
  `getNetBufferLength()`, ...) where one exists.
- **`TypeAdapterInterface` gained `quoteIdentifier()`.** Identifiers (table, view, column, ...
  names) are now escaped wherever they are interpolated into SQL, so names containing backticks
  dump correctly. Custom type adapters must implement the new method.
- **`compress-level` is validated per method.** Levels up to the method maximum are accepted
  (Gzip 1-9, Lz4 1-12, Zstd 1-22) where 2.x rejected everything above 9, and a level above the
  method maximum now throws with a method-specific message.
- **Exceptions are now typed.** The library throws subclasses of
  `Druidfi\Mysqldump\Exception\MysqldumpException` instead of bare `Exception`. No action is
  needed — the base class extends `Exception`, so existing `catch (\Exception $e)` blocks keep
  working — but you can now catch more specific types, see [Error handling](#error-handling).

Also fixed in 3.x with no action needed: 2.x settings validation rejected the `Zstd`, `Lz4` and
`Gzipstream` compression methods due to a mismatch between the allowed values and the factory;
they now validate correctly.

## Installing

Install using [Composer](https://getcomposer.org/):

```console
composer require druidfi/mysqldump-php
```

## Getting started

```php
<?php

try {
    $dump = new \Druidfi\Mysqldump\Mysqldump('mysql:host=localhost;dbname=testdb', 'username', 'password');
    $dump->start('storage/work/dump.sql');
} catch (\Druidfi\Mysqldump\Exception\MysqldumpException $e) {
    echo 'mysqldump-php error: ' . $e->getMessage();
}
```

The sections below cover the most common use cases. All configuration options are listed under
[Dump Settings](#dump-settings), and the [Tests](#tests) section describes how the output is
compared against native `mysqldump`.

## Error handling

All exceptions thrown by the library extend `Druidfi\Mysqldump\Exception\MysqldumpException`,
which itself extends the native `Exception`. Catch the base class to handle any library error,
or a subclass to react to a specific failure:

- `ConnectionException` — the database connection could not be established: malformed DSN
  string or a PDO connection failure. Thrown from the constructor (DSN parsing) and from
  `start()` (connecting).
- `ConfigurationException` — invalid dump settings: unknown options, values failing validation,
  conflicting options (e.g. `replace` + `insert-ignore`), include-tables that do not exist in
  the database, or a compression method whose PHP extension is not installed.
- `DumpException` — the dump itself failed: output file not writable, a write error (e.g. disk
  full), or an unexpected result from the server while reading object structures.

## Providing your own database connection

By default the connection is built from the DSN, username and password given to the constructor.
If you need to reuse an existing PDO instance or apply a custom connection strategy, implement
`Druidfi\Mysqldump\ConnectionInterface` and inject it before starting the dump:

```php
use Druidfi\Mysqldump\ConnectionInterface;
use Druidfi\Mysqldump\Mysqldump;

class ExistingPdoConnection implements ConnectionInterface
{
    public function __construct(private readonly PDO $pdo) {}

    public function connect(): PDO { return $this->pdo; }
    public function getHost(): string { return 'app-db'; }
    public function getDbName(): string { return 'testdb'; }
}

$dumper = new Mysqldump('mysql:host=localhost;dbname=testdb', 'username', 'password');
$dumper->setConnector(new ExistingPdoConnection($pdo));
$dumper->start('storage/work/dump.sql');
```

`getHost()` and `getDbName()` are used in the dump file header comments and in the
`SHOW`/`CREATE DATABASE` statements, so return the values of the database being dumped.

## Changing values when exporting

You can register a callable that will be used to transform values during the export. An example use-case for this is
removing sensitive data from database dumps:

```php
$dumper = new \Druidfi\Mysqldump\Mysqldump('mysql:host=localhost;dbname=testdb', 'username', 'password');

$dumper->setTransformTableRowHook(function ($tableName, array $row) {
    if ($tableName === 'customers') {
        $row['social_security_number'] = (string) rand(1000000, 9999999);
    }

    return $row;
});

$dumper->start('storage/work/dump.sql');
```

## Getting information about the dump

You can register a callable that will be used to report on the progress of the dump

```php
$dumper->setInfoHook(function ($object, $info) {
    if ($object === 'table') {
        echo $info['name'], ': ', $info['rowCount'], PHP_EOL;
    }
});
```

For tables the `$info` array contains `name`, `rowCount` and `completed` (set to `true` once the
table has been fully dumped).

## Table specific export conditions

You can register table specific 'where' clauses to limit data on a per table basis.  These override the default `where`
dump setting:

```php
$dumper = new \Druidfi\Mysqldump\Mysqldump('mysql:host=localhost;dbname=testdb', 'username', 'password');

$dumper->setTableWheres([
    'users' => 'date_registered > NOW() - INTERVAL 3 MONTH AND deleted=0',
    'logs' => 'date_logged > NOW() - INTERVAL 1 DAY',
    'posts' => 'isLive=1'
]);
```

> [!WARNING]
> The `where` dump setting, `setTableWheres()` and `setTableLimits()` values are inserted into
> the dump's `SELECT` statements as raw SQL by design — that is what makes arbitrary conditions
> possible. They are not escaped or validated, so never build them from untrusted input
> (request parameters, user-supplied data, etc.).

## Table specific export limits

You can register table specific 'limits' to limit the returned rows on a per table basis:

```php
$dumper = new \Druidfi\Mysqldump\Mysqldump('mysql:host=localhost;dbname=testdb', 'username', 'password');

$dumper->setTableLimits([
    'users' => 300,
    'logs' => 50,
    'posts' => 10
]);
```
You can also specify the limit as a two-value array, which maps to MySQL's `LIMIT offset, row_count`
syntax: the first value is the offset and the second is the number of rows

```php
$dumper = new \Druidfi\Mysqldump\Mysqldump('mysql:host=localhost;dbname=testdb', 'username', 'password');

$dumper->setTableLimits([
    'users' => [20, 10], // MySQL query equivalent "... LIMIT 20, 10", i.e. 10 rows starting from offset 20
]);
```
## Dump Settings

Dump settings can be changed from default values with the 4th argument of the Mysqldump constructor.
PDO options can be passed as the 5th argument:

```php
$dumpSettings = ['compress' => 'Gzip', 'no-data' => true];

$dumper = new \Druidfi\Mysqldump\Mysqldump('mysql:host=localhost;dbname=testdb', 'username', 'password', $dumpSettings, $pdoOptions);
```

All options:

- **include-tables**
  - Only include these tables (array of table names), include all if empty.
- **exclude-tables**
  - Exclude these tables (array of table names), include all if empty, supports regexps.
- **include-views**
  - Only include these views (array of view names), include all if empty. By default, all views named as the include-tables array are included.
- **if-not-exists**
  - Only create a new table when a table of the same name does not already exist. No error message is thrown if the table already exists. 
- **compress**
  - Possible values: `Bzip2|Gzip|Gzipstream|Zstd|Lz4|None`, default is `None`
  - Could be specified using the `CompressMethod` enum values (e.g. `CompressMethod::Gzip->value`) or the consts: `CompressManagerFactory::GZIP`, `CompressManagerFactory::BZIP2`, `CompressManagerFactory::GZIPSTREAM`, `CompressManagerFactory::ZSTD`, `CompressManagerFactory::LZ4` or `CompressManagerFactory::NONE`
  - `Zstd` requires `ext-zstd` and `Lz4` requires `ext-lz4`
- **compress-level**
  - Compression level to use (integer), default is `0` (use the method's own default level)
  - For Gzip: 1-9
  - For Zstd: 1-22 (default: 3)
  - For Lz4: 1-12 (default: 1)
- **reset-auto-increment**
  - Removes the AUTO_INCREMENT option from the database definition
  - Useful when used with no-data, so when db is recreated, it will start from 1 instead of using an old value
- **add-drop-database**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_add-drop-database)
- **add-drop-table**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_add-drop-table)
- **add-drop-triggers**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_add-drop-trigger)
- **add-locks**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_add-locks)
- **complete-insert**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_complete-insert)
- **databases**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_databases)
- **default-character-set**
  - Possible values: `utf8|utf8mb4`, default is `utf8`
  - `utf8` is compatible option and `utf8mb4` is for full utf8 compliance
  - Could be specified using the consts: `DumpSettings::UTF8` or `DumpSettings::UTF8MB4`
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/charset-unicode-utf8mb4.html)
- **disable-keys**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_disable-keys)
- **events**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_events)
- **extended-insert**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_extended-insert)
- **hex-blob**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_hex-blob)
- **insert-ignore**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_insert-ignore)
- **replace**
  - Use REPLACE INTO instead of INSERT INTO statements. Cannot be used together with insert-ignore.
- **lock-tables**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_lock-tables)
- **net_buffer_length**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_net-buffer-length)
- **no-autocommit**
  - Option to disable autocommit (faster inserts, no problems with index keys)
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/commit.html)
- **no-create-info**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_no-create-info)
- **no-data**
  - Do not dump data for these tables (array of table names), support regexps, `true` to ignore all tables
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_no-data)
- **routines**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_routines)
- **single-transaction**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_single-transaction)
- **skip-comments**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_skip-comments)
- **skip-dump-date**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_dump-date)
- **skip-triggers**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_triggers)
- **skip-tz-utc**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_tz-utc)
- **skip-definer**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqlpump.html#option_mysqlpump_skip-definer)
- **where**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html#option_mysqldump_where)
  - Raw SQL by design — see the warning under [Table specific export conditions](#table-specific-export-conditions)

The following option is deprecated. Passing it triggers a deprecation notice, and it will be removed
in a future version; use `init_commands` to control `FOREIGN_KEY_CHECKS` manually if needed.

- **disable-foreign-keys-check**
  - MySQL docs [8.0](https://dev.mysql.com/doc/refman/8.0/en/optimizing-innodb-bulk-data-loading.html)

## Privileges

To dump a database, you need the following privileges:

- **SELECT**
  - In order to dump table structures and data.
- **SHOW VIEW**
  - If any databases has views, else you will get an error.
- **TRIGGER**
  - If any table has one or more triggers.
- **LOCK TABLES**
  - If "lock tables" option was enabled.
- **PROCESS**
  - If you don’t use the --no-tablespaces option.

Use **SHOW GRANTS FOR user@host;** to know what privileges user has. See the following link for more information:

- [Which are the minimum privileges required to get a backup of a MySQL database schema?](https://dba.stackexchange.com/questions/55546/which-are-the-minimum-privileges-required-to-get-a-backup-of-a-mysql-database-sc/55572#55572)
- [PROCESS privilege from MySQL 5.7.31 and MySQL 8.0.21 in July 2020](https://anothercoffee.net/how-to-fix-the-mysqldump-access-denied-process-privilege-error/)

## Tests

The testing script creates and populates a database using all possible datatypes. Then it exports it using both
mysqldump-php and mysqldump, and compares the output. Only if it is identical tests are OK.

Some tests are skipped if mysql server doesn't support them.

A couple of tests are only comparing between original sql code and mysqldump-php generated sql, because some options
are not available in mysqldump.

Local setup for tests:

```console
composer install
docker compose up --wait --build
docker compose exec -w /app/tests/scripts php84 ./test.sh mysql
docker compose exec -w /app/tests/scripts php85 ./test.sh mysql
docker compose exec -w /app/tests/scripts php84 ./test.sh mysql84
docker compose exec -w /app/tests/scripts php85 ./test.sh mysql84
docker compose exec -w /app/tests/scripts php84 ./test.sh mariadb
docker compose exec -w /app/tests/scripts php85 ./test.sh mariadb
```

## Credits

Forked from Diego Torres's version which have latest updates from 2020. Use it for PHP 8.1 and older.
https://github.com/ifsnop/mysqldump-php

Originally based on James Elliott's script from 2009.
https://code.google.com/archive/p/db-mysqldump/

Adapted and extended by Michael J. Calkins.
https://github.com/clouddueling

## License

This project is open-sourced software licensed under the [GPL license](https://www.gnu.org/copyleft/gpl.html)
