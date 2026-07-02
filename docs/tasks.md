# Improvement Tasks for mysqldump-php (3.x / PHP 8.4+)

This document contains a detailed list of actionable improvement tasks for the 3.x line (`main` branch, PHP 8.4+).
Each task is marked with a checkbox that can be checked off when completed. Tasks are grounded in the current
codebase; items that are done are kept checked for history.

## Architecture Improvements

1. [ ] Refactor the large Mysqldump class (~1090 lines) into smaller, more focused classes:
   - [x] Create a separate DatabaseConnector class to handle connection logic
   - [x] Create a separate DumpWriter class to handle file output
   - [x] Create separate classes for different database object types (Tables, Views, Triggers, etc.)
   - [ ] Extract table data dumping (`listValues()`, `prepareListValues()`, `endListValues()`,
         `prepareColumnValues()`, `getColumnStmt()`, `getColumnNames()`) into a dedicated class
   - [ ] Remove the vestigial `getDatabaseStructure*()` methods: five are empty no-ops and
         `getDatabaseStructureTables()` only validates include-tables (rename to `validateIncludedTables()`)
   - [ ] Deduplicate the six near-identical `iterate*()` generators into one shared helper
         (query + column extraction + optional include filter)

2. [ ] Improve dependency wiring:
   - [ ] Fix `Mysqldump::$adapterClass` being `static` — `addTypeAdapter()` leaks the adapter
         across all instances in the same process; make it an instance property
   - [ ] Pass ObjectDumper dependencies more explicitly instead of six positional closures
         (e.g. a small context object or named-argument value object)
   - [ ] Reconsider `PDO::ATTR_PERSISTENT => true` as a hardcoded default in DatabaseConnector

3. [ ] Improve error handling:
   - [ ] Create custom exception classes (e.g. `ConnectionException`, `ConfigurationException`,
         `DumpException`) extending a common `MysqldumpException`; everything currently throws bare `Exception`
   - [ ] Implement a proper exception hierarchy and document which methods throw what

4. [ ] Implement interfaces for major components:
   - [x] Create a DumperInterface for different dumper implementations
   - [ ] Create a ConnectionInterface so DatabaseConnector can be swapped/mocked

## PHP 8.4 Modernization (new in 3.x)

5. [ ] Adopt PHP 8.4 language features now that older PHP support is dropped:
   - [ ] Use constructor property promotion in `DatabaseConnector` and `Mysqldump`
         (both still declare classic properties and assign in the constructor)
   - [ ] Add typed class constants (PHP 8.3+): e.g. `const string UTF8 = 'utf8'` in `DumpSettings`,
         and the string constants in `ConfigOption`
   - [ ] Type the hook properties as `?Closure` (`$transformTableRowCallable`,
         `$transformColumnValueCallable`, `$infoCallable` are currently untyped)
   - [ ] Add missing return types: `getTableStructure()`, `getViewStructureTable()`,
         `getViewStructureView()`, `getTriggerStructure()`, `getProcedureStructure()`,
         `getFunctionStructure()`, `getEventStructure()`, `listValues()`, `prepareListValues()`,
         `endListValues()`, `setTransformColumnValueHook()` lack `: void`
   - [ ] Evaluate replacing the custom `Attribute\Deprecated` with the native PHP 8.4
         `#[\Deprecated]` attribute (native triggers automatic deprecation notices; keep the
         custom one only if the extra `removeIn`/`alternative` metadata is worth it)
   - [ ] Use `array_any()` / `array_find()` (PHP 8.4) to simplify `matches()`
   - [ ] Evaluate property hooks / asymmetric visibility where they simplify getters
         (e.g. `DatabaseConnector::$host` / `$dbName`)

6. [ ] Introduce enums for closed value sets:
   - [ ] Backed enum for compression methods — this would also fix the current drift:
         `ConfigOption::COMPRESS` allows `['None', 'Gzip', 'Bzip2', 'Zstandard', 'LZ4']` while
         `CompressManagerFactory` uses `Gzipstream`, `Zstd`, `Lz4`
   - [ ] Enum for insert type (`INSERT` / `INSERT IGNORE` / `REPLACE`) replacing `getInsertType()` strings

7. [ ] Tighten tooling now that the floor is PHP 8.4:
   - [ ] Enable `->withPhpSets()` in `rector.php` (currently commented out) and raise
         `withTypeCoverageLevel` / `withDeadCodeLevel` / `withCodeQualityLevel` from 0
   - [ ] Raise PHPStan from level 4 towards 6+ and fix findings

## Code Quality Improvements

8. [ ] Reduce code duplication:
   - [ ] Refactor the near-identical `getProcedureStructure()` / `getFunctionStructure()` /
         `getEventStructure()` / `getTriggerStructure()` "show create + write" pattern
   - [ ] Extract the repeated comment-header writing (`skipComments()` + sprintf block) into a helper

9. [ ] Improve method organization:
   - [ ] Break up `listValues()` (~95 lines) into smaller private methods
   - [ ] Group related methods together in `Mysqldump.php`

10. [x] Enhance type safety (baseline):
    - [x] Add return type declarations to public API methods
    - [x] Add parameter type declarations to all methods
    - [x] Use strict typing throughout the codebase
    - Note: remaining gaps are itemized under task 5

11. [ ] Implement better validation:
    - [ ] `matches()` reads `$pattern[0]` — an empty string in include/exclude/no-data arrays causes
          an error; validate patterns up front
    - [ ] Fix `DumpSettings::get()` casting every setting to `string` — return proper types or add
          typed getters
    - [ ] Validate `net_buffer_length` and other numeric settings via `Constraint` attributes

## Documentation Improvements

12. [ ] Improve code documentation:
    - [ ] Document `@throws` consistently once custom exceptions exist (task 3)
    - [ ] Remove stale PHPDoc (e.g. `getDatabaseStructure*` docblocks say "Fills $this->tables array",
          which no longer exists)

13. [ ] Improve user documentation (README already covers install, hooks, settings, privileges):
    - [ ] Document 2.x → 3.x upgrade / breaking changes
    - [ ] Replace references to the ifsnop wiki with own examples
    - [ ] Document compression options incl. optional ext-zstd / ext-lz4 requirements
    - [ ] Document dumping to stream wrappers (gs://, s3:// via league/flysystem etc.)
          instead of adding cloud SDK dependencies to the library

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
          `complete-insert`, `no-data` patterns, `skip-definer`)
    - [ ] Add MariaDB 11.x LTS to the matrix when supported by the test images

16. [ ] Add performance testing:
    - [ ] Benchmark dump operations against a large fixture database
    - [ ] Compare speed/memory against native mysqldump and document results

## Performance Improvements

17. [ ] Optimize memory usage:
    - [x] Review and optimize large data structures
    - [x] Use generators for large result sets
    - [ ] Make the `iterate*()` generators truly streaming — they currently buffer all names into
          an array before yielding
    - [ ] Review `$tableColumnTypes` growth on databases with many tables/columns

## Security Improvements

18. [ ] Harden identifier and input handling (classic prepared statements don't apply to
        identifiers or to a dump tool's SHOW/SELECT flow):
    - [ ] Escape/validate table names consistently when interpolated into SQL
          (e.g. `` `$tableName` `` in `listValues()`; a name containing a backtick would break the query)
    - [ ] Document that `where`, `tableWheres` and `tableLimits` are raw SQL by design and must not
          contain untrusted input

19. [ ] Improve security posture of the project:
    - [ ] Add a SECURITY.md with a vulnerability reporting policy
    - [ ] Document secure credential practices (env vars, secret managers) in the README
          — actual credential storage/rotation is the caller's responsibility, not the library's

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
    - [ ] Document the info hook payload shape in the README

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
