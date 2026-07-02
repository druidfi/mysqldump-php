# CLAUDE.md

This file provides guidance for AI assistants working with the mysqldump-php codebase.

## Project Overview

**mysqldump-php** is a pure PHP implementation of the MySQL `mysqldump` CLI tool. It creates database backups and dumps without requiring the native mysqldump binary, allowing data manipulation before dump creation (e.g., anonymization).

- **Package**: `druidfi/mysqldump-php`
- **License**: GPL-3.0-or-later
- **PHP**: ^8.1 with PDO extension
- **Databases**: MySQL 8.0+, MariaDB 10.11+

## Codebase Structure

```
src/
├── Mysqldump.php              # Main orchestrator class (~1100 lines)
├── DumpSettings.php           # Configuration management
├── DumpWriter.php             # File output handler
├── DatabaseConnector.php      # PDO connection management
├── ConfigValidator.php        # Settings validation via reflection
├── ConfigOption.php           # Config constants with PHP 8 attributes
├── Attribute/                 # PHP 8 attribute definitions
│   ├── Constraint.php         # Validation rules
│   ├── DefaultValue.php       # Default values with descriptions
│   ├── Deprecated.php         # Deprecation metadata
│   ├── Injectable.php         # DI marker
│   └── ValidatesValue.php     # Validation marker
├── Compress/                  # Compression implementations
│   ├── CompressInterface.php  # Common interface
│   ├── CompressManagerFactory.php
│   ├── CompressNone.php
│   ├── CompressGzip.php
│   ├── CompressBzip2.php
│   ├── CompressGzipstream.php
│   ├── CompressZstd.php       # Optional ext-zstd
│   └── CompressLz4.php        # Optional ext-lz4
├── ObjectDumper/              # Strategy pattern for dump types
│   ├── DumperInterface.php
│   ├── TablesDumper.php
│   ├── ViewsDumper.php
│   ├── TriggersDumper.php
│   ├── RoutinesDumper.php
│   └── EventsDumper.php
└── TypeAdapter/               # Database-specific SQL generation
    ├── TypeAdapterInterface.php
    └── TypeAdapterMysql.php

tests/
├── *Test.php                  # PHPUnit test files
├── Doubles/                   # Test doubles
└── scripts/                   # Integration test scripts
    ├── test.sh                # Main integration test runner
    ├── test.php               # PHP-side dump runner used by test.sh
    ├── pdo_checks.php         # PDO environment sanity checks
    ├── test*.src.sql          # SQL test fixtures
    └── output/                # Generated and expected dump outputs

docs/
└── tasks.md                   # Improvement roadmap (checkbox task list)

# Root-level Docker test setup
Dockerfile                     # PHP test container (PHP_SHORT_VERSION build arg)
compose.yaml                   # Services: mysql, mariadb, php81..php85
config/skip-ssl.cnf            # MySQL client config for test containers
docker-entrypoint-initdb.d/    # DB init scripts (mysql-init.sql, mariadb-init.sql)
.env.mysql                     # Shared DB credentials for compose services
```

## Development Commands

```bash
# Install dependencies
composer install

# Run PHPUnit tests
vendor/bin/phpunit

# Run static analysis (level 4)
vendor/bin/phpstan

# Run code modernization check (dry-run)
vendor/bin/rector process --dry-run

# Run integration tests (requires database)
cd tests/scripts && ./test.sh 127.0.0.1

# Docker-based testing
docker compose up mysql php81    # or php82, php83, php84, php85
docker compose up mariadb php81  # same, against MariaDB
```

## Key Coding Conventions

### Strict Typing
All PHP files must use strict types:
```php
<?php

declare(strict_types=1);

namespace Druidfi\Mysqldump;
```

### Type Declarations
- All methods require explicit return type declarations
- All parameters must be fully typed
- Use `?Type` for nullable parameters, not implicit nullability
- Use union types where appropriate: `string|false`

### PHP 8 Attributes
Configuration options use PHP 8 attributes for metadata:
```php
#[DefaultValue(value: 'None', description: 'Compression method')]
#[Constraint(allowedValues: ['None', 'Gzip', 'Bzip2', 'Gzipstream', 'Zstd', 'Lz4'])]
public const COMPRESS = 'compress';

#[Deprecated(reason: 'Renamed', since: '2.0', alternative: 'no-create-info')]
public const DISABLE_FOREIGN_KEYS_CHECK = 'disable-foreign-keys-check';
```

### Design Patterns Used

1. **Strategy Pattern** (`ObjectDumper/`): Different dumpers for tables, views, triggers, routines, events
2. **Factory Pattern** (`CompressManagerFactory`): Creates compression handlers by method name
3. **Type Adapter Pattern** (`TypeAdapter/`): Database-specific SQL generation
4. **Closure Callbacks**: Used to avoid tight coupling between components

### Namespace
```
Druidfi\Mysqldump\
├── Attribute\
├── Compress\
├── ObjectDumper\
└── TypeAdapter\
```

## Testing

### PHPUnit Tests
- Located in `tests/` directory
- Run with: `vendor/bin/phpunit`
- Use `FakeTypeAdapter` for mocking database operations
- Access private properties via reflection when necessary

### Integration Tests
- Compare mysqldump-php output against native mysqldump
- Tests must produce identical output to pass
- Run against MySQL 8.0 and MariaDB 10.11
- Test fixtures in `tests/scripts/test*.src.sql`

### CI Matrix
Tests run on:
- PHP: 8.1, 8.2, 8.3, 8.4, 8.5
- Databases: MySQL 8.0, MariaDB 10.11
- Total: 10 combinations

## Static Analysis

### PHPStan (Level 4)
```bash
vendor/bin/phpstan
```
- Configured in `phpstan.dist.neon`
- Ignores errors for optional extensions (ext-zstd, ext-lz4)
- Analyzes `src/` and `tests/`
- **Not a direct dependency**: `phpstan/phpstan` is not in `require-dev`; it is available transitively via `rector/rector`
- **Advisory in CI**: the PHPStan step runs with `continue-on-error: true`, so failures do not block PRs

### Rector
```bash
vendor/bin/rector process --dry-run
```
- Configured in `rector.php`
- Target: PHP 8.1
- Used for code modernization checks

## Architecture Notes

### Main Data Flow
```
Mysqldump.start()
  → DatabaseConnector (PDO connection)
  → DumpWriter (output with compression)
  → TypeAdapter (SQL generation)
  → ObjectDumpers (tables, views, triggers, routines, events)
```

### Public API (Mysqldump class)
```php
// Constructor
new Mysqldump(string $dsn, string $user, string $pass, array $settings, array $pdoOptions);

// Execute dump
$dump->start(?string $filename);

// Data filtering
$dump->setTableWheres(array $tableWheres);
$dump->getTableWhere(string $tableName);
$dump->setTableLimits(array $tableLimits);
$dump->getTableLimit(string $tableName);

// Data transformation hooks
$dump->setTransformTableRowHook(callable $hook);
$dump->setTransformColumnValueHook(callable $hook);
$dump->setInfoHook(callable $hook);

// Type adapter extension
$dump->addTypeAdapter(string $adapterClassName);
$dump->getAdapter(PDO $conn);
```

### Error Handling
- Uses standard `Exception` class (no custom hierarchy yet)
- PDO connection errors caught and re-thrown with context
- Validation errors from `ConfigValidator` have specific messages

## Common Tasks

### Adding a New Dump Setting
1. Add constant to `src/ConfigOption.php` with attributes
2. Add handling logic in `src/DumpSettings.php`
3. Implement behavior in `src/Mysqldump.php`
4. Add tests in `tests/DumpSettingsTest.php`

### Adding a New Compression Method
1. Create class in `src/Compress/` implementing `CompressInterface`
2. Add constant to `CompressManagerFactory::METHODS`
3. If optional extension, add PHPStan ignore in `phpstan.dist.neon`
4. Add tests

### Adding a New Object Dumper
1. Create class in `src/ObjectDumper/` implementing `DumperInterface`
2. Integrate with `Mysqldump.php` main class
3. Add appropriate SQL methods to `TypeAdapterMysql.php`

## Important Files

| File | Purpose |
|------|---------|
| `src/Mysqldump.php` | Main entry point and orchestration |
| `src/ConfigOption.php` | All configuration option constants |
| `src/DumpSettings.php` | Configuration validation and defaults |
| `src/TypeAdapter/TypeAdapterMysql.php` | MySQL-specific SQL generation |
| `phpstan.dist.neon` | Static analysis configuration |
| `rector.php` | Code modernization rules |
| `.github/workflows/tests.yml` | CI/CD pipeline |
| `docs/tasks.md` | Improvement roadmap (refactoring direction, e.g. planned custom exceptions) |

## Gotchas

1. **Optional Extensions**: `ext-zstd` and `ext-lz4` are optional; code must handle their absence gracefully
2. **PHP 8.5 Deprecation**: `MYSQL_ATTR_USE_BUFFERED_QUERY` is deprecated in PHP 8.5; handled in `DatabaseConnector`
3. **Integration Tests**: Output must match native mysqldump exactly (whitespace-sensitive)
4. **Large Mysqldump Class**: The main class is ~1100 lines; consider impact when modifying
5. **Closure Callbacks**: ObjectDumpers receive closures, not direct dependencies

## Links

- Repository: https://github.com/druidfi/mysqldump-php
- Original fork: https://github.com/ifsnop/mysqldump-php
