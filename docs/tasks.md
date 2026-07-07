# Improvement Tasks for mysqldump-php (3.x / PHP 8.4+)

This document contains a detailed list of actionable improvement tasks for the 3.x line (`main` branch, PHP 8.4+).
Each task is marked with a checkbox that can be checked off when completed. Tasks are grounded in the current
codebase; items that are done are kept checked for history.

## Architecture Improvements

1. [x] Refactor the large Mysqldump class (~1090 lines, now ~750) into smaller, more focused classes:
   - [x] Create a separate DatabaseConnector class to handle connection logic
   - [x] Create a separate DumpWriter class to handle file output
   - [x] Create separate classes for different database object types (Tables, Views, Triggers, etc.)
   - [x] Extract table data dumping (`listValues()`, `prepareListValues()`, `endListValues()`,
         `prepareColumnValues()`, `getColumnStmt()`, `getColumnNames()`) into a dedicated
         `TableDataDumper` class (state still living on Mysqldump — column types, wheres/limits,
         hooks — is passed in as closures; unit-tested against SQLite in `TableDataDumperTest`)
   - [x] Remove the vestigial `getDatabaseStructure*()` methods: five were empty no-ops and
         `getDatabaseStructureTables()` only validated include-tables (now `validateIncludedTables()`)
   - [x] Deduplicate the six near-identical `iterate*()` generators into one shared
         `iterateObjectNames()` helper (query + column extraction + optional include filter)

2. [x] Improve dependency wiring:
   - [x] Fix `Mysqldump::$adapterClass` being `static` — now an instance property, with a
         regression test that `addTypeAdapter()` no longer leaks across instances
   - [x] Pass ObjectDumper dependencies more explicitly — dumpers are constructed with named
         arguments; a separate context class was considered and skipped as over-engineering
   - [x] `PDO::ATTR_PERSISTENT => true` removed from the connection defaults — one-shot dumps
         gain nothing from persistence and recycled handles can carry over session state;
         opt back in via the `pdoOptions` constructor argument

3. [x] Improve error handling:
   - [x] Create custom exception classes (`ConnectionException`, `ConfigurationException`,
         `DumpException`) extending a common `MysqldumpException` in `src/Exception/`;
         the base extends native `Exception` so existing catch blocks keep working
   - [x] Implement a proper exception hierarchy and document which methods throw what
         (`@throws` docblocks updated throughout; README "Error handling" section added)

4. [x] Implement interfaces for major components:
   - [x] Create a DumperInterface for different dumper implementations
   - [x] Create a ConnectionInterface so DatabaseConnector can be swapped/mocked —
         `Mysqldump::setConnector()` injects a custom implementation before `start()`;
         `ConnectionInterfaceTest` runs a full dump against injected SQLite to prove it

## PHP 8.4 Modernization (new in 3.x)

5. [x] Adopt PHP 8.4 language features now that older PHP support is dropped:
   - [x] Use constructor property promotion where properties are assigned directly
         (`DatabaseConnector`, `DumpWriter`, the `ObjectDumper` classes, `TypeAdapterMysql`;
         `Mysqldump` derives its properties in the constructor, so promotion does not apply)
   - [x] Add typed class constants (PHP 8.3+) in `DumpSettings`, `ConfigOption` and
         `CompressManagerFactory`
   - [x] Type the hook properties as `?Closure` (`$transformTableRowCallable`,
         `$transformColumnValueCallable`, `$infoCallable`)
   - [x] Add missing `: void` return types on the structure/data extractor methods and
         `setTransformColumnValueHook()`
   - [x] Replace the custom `Attribute\Deprecated` with the native PHP 8.4 `#[\Deprecated]`
         attribute (the `alternative` field folded into `message`)
   - [x] Use `array_any()` (PHP 8.4) to simplify `matches()` — also fixed the warning on
         empty-string patterns
   - [x] Property hooks / asymmetric visibility evaluated: getters kept as-is to avoid
         changing the public API for no functional gain

6. [x] Introduce enums for closed value sets:
   - [x] `CompressMethod` backed enum as single source of truth for compression methods;
         the `Constraint` attribute gained an `enum` parameter and the compress option now
         validates against it — this fixed a drift bug where `ConfigOption` allowed
         `'Zstandard'`/`'LZ4'` while the factory expected `'Zstd'`/`'Lz4'`, so Zstd, Lz4 and
         Gzipstream were rejected at validation
   - [x] `InsertType` backed enum replacing `getInsertType()` strings

7. [x] Tighten tooling now that the floor is PHP 8.4:
   - [x] Enable `->withPhpSets()` in `rector.php` and raise `withTypeCoverageLevel` /
         `withDeadCodeLevel` / `withCodeQualityLevel` from 0 to 10
   - [x] Raise PHPStan to level 6 and fix findings (mostly missing iterable value types
         in PHPDoc); level 6 now passes clean and is blocking in CI

## Code Quality Improvements

8. [x] Reduce code duplication:
   - [x] Refactor the near-identical `getProcedureStructure()` / `getFunctionStructure()` /
         `getEventStructure()` / `getTriggerStructure()` "show create + write" pattern into a
         shared `writeStructureFromShowCreate()` helper — also applied to the table and view
         structure extractors, which had the same shape
   - [x] Extract the repeated comment-header writing (`skipComments()` + sprintf block) into
         `commentBlock()` / `writeComment()` helpers

9. [x] Improve method organization:
   - [x] Break up `TableDataDumper::dump()` (~95 lines, formerly `Mysqldump::listValues()`)
         into smaller private methods (`buildSelectQuery()` + `writeInsertStatements()`;
         `dump()` itself is now ~25 lines of orchestration)
   - [x] Group related methods together in `Mysqldump.php` — `getAdapter()` moved next to
         `addTypeAdapter()` and the `tableColumnTypes()` accessor next to
         `getTableColumnTypes()`; the file reads top-down as dump flow → helpers →
         iterators → structure extractors → public configuration API

10. [x] Enhance type safety (baseline):
    - [x] Add return type declarations to public API methods
    - [x] Add parameter type declarations to all methods
    - [x] Use strict typing throughout the codebase
    - Note: remaining gaps are itemized under task 5

11. [ ] Implement better validation:
    - [x] `matches()` reads `$pattern[0]` — an empty string in include/exclude/no-data arrays causes
          an error (fixed via `str_starts_with()` in the `array_any()` rewrite)
    - [x] Fix `DumpSettings::get()` casting every setting to `string` — `get()` now returns the
          raw value as `mixed` (`null` for unknown options) and the one string consumer uses a
          new `getWhere()` typed getter; documented as a breaking change in the README
    - [x] Validate `net_buffer_length` and other numeric settings via `Constraint` attributes
          (`net_buffer_length` has `min: 1024`, `compress-level` has `min: 0, max: 22` —
          the only two numeric options)
    - [x] Align the `compress-level` constraint with the real level ranges — the attribute allows
          the global 0–22 range and `DumpSettings` enforces the per-method maximum via
          `CompressMethod::maxLevel()` (Gzip 9, Lz4 12, Zstd 22)

## Documentation Improvements

12. [x] Improve code documentation:
    - [x] Document `@throws` consistently with the custom exception classes from task 3
    - [x] Remove stale PHPDoc — the `getDatabaseStructure*` docblocks claiming to fill a
          non-existent `$this->tables` array went away with the methods themselves (task 1)

13. [x] Improve user documentation (README already covers install, hooks, settings, privileges):
    - [x] Document 2.x → 3.x upgrade / breaking changes (README "Upgrading from 2.x to 3.x")
    - [x] Replace references to the ifsnop wiki with own examples
    - [x] Document compression options incl. optional ext-zstd / ext-lz4 requirements
    - [x] Document dumping to stream wrappers (gs://, s3:// via league/flysystem etc.)
          instead of adding cloud SDK dependencies to the library — README "Dumping to cloud
          storage and other streams" section: `start()` opens the target with `fopen()`, so any
          registered wrapper works; notes that `Gzip`/`Bzip2` are local-only and `Gzipstream`
          is the wrapper-safe gzip

## Testing Improvements

14. [x] Enhance unit testing:
    - [x] Increase test coverage for core functionality
    - [x] Add tests for edge cases
    - [x] Implement proper mocking for dependencies

15. [ ] Extend integration testing (CI already runs PHP 8.4/8.5 × MySQL 8.0/8.4/MariaDB 10.11 and
        compares output against native mysqldump):
    - [x] Test with different database versions
    - [x] Test with different PHP versions
    - [ ] Broaden dump-settings coverage in `tests/scripts/test.sh` (e.g. compression methods,
          `no-data` patterns, `skip-definer`; `complete-insert` is already covered)
    - [x] Add MariaDB 11.x LTS to the matrix — `mariadb:11.8` added to compose.yaml
          (`mariadb11` service) and to CI (8 combinations total); the filtered diffs proved
          immune to the 11.x output drift, but the image drops the `mysql`/`mysqldump`
          compatibility symlinks, so the CI readiness gate picks the client binary per database

16. [ ] Add performance testing:
    - [ ] Benchmark dump operations against a large fixture database
    - [ ] Compare speed/memory against native mysqldump and document results

## Performance Improvements

17. [ ] Optimize memory usage:
    - [x] Review and optimize large data structures
    - [x] Use generators for large result sets
    - [x] `iterate*()` generator buffering reviewed and kept as deliberate: only object *names*
          are buffered (tiny), and the cursor must be closed before consumers run their own
          queries per object — documented in `iterateObjectNames()`
    - [ ] Review `$tableColumnTypes` growth on databases with many tables/columns

## Security Improvements

18. [x] Harden identifier and input handling (classic prepared statements don't apply to
        identifiers or to a dump tool's SHOW/SELECT flow):
    - [x] Escape/validate table names consistently when interpolated into SQL —
          `quoteIdentifier()` added to `TypeAdapterInterface` (backtick-doubling in
          `TypeAdapterMysql`) and applied to every identifier interpolation: SELECT/INSERT in
          `TableDataDumper`, SHOW CREATE/DROP/LOCK/ALTER in `TypeAdapterMysql`, Stand-In tables;
          INFORMATION_SCHEMA database-name string literals now go through `PDO::quote()`
    - [x] Document that `where`, `tableWheres` and `tableLimits` are raw SQL by design and must not
          contain untrusted input (README warning + setter docblocks + `ConfigOption` description)

19. [x] Improve security posture of the project:
    - [x] Add a SECURITY.md with a vulnerability reporting policy — supported versions,
          GitHub private vulnerability reporting as the channel, and a scope note that the
          raw-SQL settings (`where`/`tableWheres`/`tableLimits`) are by design
    - [x] Document secure credential practices (env vars, secret managers) in the README
          ("Security considerations" section with a `getenv()` example) — actual credential
          storage/rotation stays the caller's responsibility, not the library's

## Feature Enhancements

20. [x] Add new compression options:
    - [x] Support for Zstandard compression
    - [x] Support for LZ4 compression
    - [x] Configurable compression levels

21. [ ] Enhance data transformation capabilities:
    - [ ] Provide ready-made anonymization helpers/recipes on top of the existing hooks
    - [ ] Support returning `null` from hooks to skip a row entirely (row filtering)

22. [ ] Improve progress reporting (an `infoHook` with per-table row counts already exists):
    - [ ] Report total table count / overall progress, not just per-table row counts
    - [x] Document the info hook payload shape in the README

## Maintenance Improvements

23. [ ] Project housekeeping:
    - [x] Automate dependency updates (Renovate is configured)
    - [ ] Decide on `squizlabs/php_codesniffer` in require-dev: it has no ruleset and is not run
          in CI — either add a PSR-12 ruleset + CI step or remove the dependency
    - [ ] Create issue templates and contribution guidelines
    - [ ] Tag a 3.0.0 release once the modernization tasks above land; add release notes template

24. [x] Implement static analysis and modernization tooling:
    - [x] PHPStan (level 4) blocking in CI
    - [x] Rector dry-run blocking in CI
    - Note: raising strictness levels is tracked in task 7

## Dropped from the previous version of this list

Removed as out of scope or not applicable to a dump library, so they stop showing up as "open work":

- *Dependency injection container* — overkill for a small library; plain constructor injection (task 2) suffices.
- *Add indexes for frequently queried data* — the library only reads schemas/data; indexing is the user's database concern.
- *Parallel processing / non-blocking I/O* — conflicts with `single-transaction` consistent-snapshot semantics.
- *Credential rotation / secure credential storage* — caller's responsibility; replaced by documentation task 19.
- *Native AWS S3 / Azure Blob support* — would add heavy SDK dependencies; stream wrappers already work
  (replaced by documentation task 13).
- *Use prepared statements consistently* — identifiers cannot be bound; replaced by identifier escaping task 18.
- *Class diagrams / architecture docs* — the codebase is ~3200 lines across small classes; CLAUDE.md and
  the README cover the architecture adequately.
