<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump;

use Druidfi\Mysqldump\Attribute\DefaultValue;
use Druidfi\Mysqldump\Attribute\Constraint;
use Druidfi\Mysqldump\Attribute\Deprecated;

/**
 * Represents configuration options with metadata using PHP 8 Attributes.
 * This class demonstrates how attributes can be used to document default values,
 * validation constraints, and deprecation information.
 */
class ConfigOption
{
    #[DefaultValue(value: [], description: 'Tables to include in the dump')]
    #[Constraint(message: 'Must be an array of table names')]
    public const INCLUDE_TABLES = 'include-tables';

    #[DefaultValue(value: [], description: 'Tables to exclude from the dump')]
    #[Constraint(message: 'Must be an array of table names')]
    public const EXCLUDE_TABLES = 'exclude-tables';

    #[DefaultValue(value: [], description: 'Views to include in the dump')]
    #[Constraint(message: 'Must be an array of view names')]
    public const INCLUDE_VIEWS = 'include-views';

    #[DefaultValue(value: 'None', description: 'Compression method to use')]
    #[Constraint(allowedValues: ['None', 'Gzip', 'Bzip2', 'Zstandard', 'LZ4'], message: 'Must be a valid compression method')]
    public const COMPRESS = 'compress';

    #[DefaultValue(value: 0, description: 'Compression level (0-9)')]
    #[Constraint(min: 0, max: 9, message: 'Compression level must be between 0 and 9')]
    public const COMPRESS_LEVEL = 'compress-level';

    #[DefaultValue(value: [], description: 'Initial SQL commands to execute')]
    #[Constraint(message: 'Must be an array of SQL commands')]
    public const INIT_COMMANDS = 'init_commands';

    #[DefaultValue(value: [], description: 'Tables to skip data export')]
    #[Constraint(message: 'Must be an array of table names')]
    public const NO_DATA = 'no-data';

    #[DefaultValue(value: false, description: 'Add IF NOT EXISTS to CREATE statements')]
    public const IF_NOT_EXISTS = 'if-not-exists';

    #[DefaultValue(value: false, description: 'Reset auto-increment values')]
    public const RESET_AUTO_INCREMENT = 'reset-auto-increment';

    #[DefaultValue(value: false, description: 'Add DROP DATABASE statement')]
    public const ADD_DROP_DATABASE = 'add-drop-database';

    #[DefaultValue(value: false, description: 'Add DROP TABLE statements')]
    public const ADD_DROP_TABLE = 'add-drop-table';

    #[DefaultValue(value: true, description: 'Add DROP TRIGGER statements')]
    public const ADD_DROP_TRIGGER = 'add-drop-trigger';

    #[DefaultValue(value: true, description: 'Add table locks')]
    public const ADD_LOCKS = 'add-locks';

    #[DefaultValue(value: false, description: 'Use complete INSERT statements with column names')]
    public const COMPLETE_INSERT = 'complete-insert';

    #[DefaultValue(value: false, description: 'Include database creation statements')]
    public const DATABASES = 'databases';

    #[DefaultValue(value: DumpSettings::UTF8, description: 'Default character set')]
    #[Constraint(allowedValues: [DumpSettings::UTF8, DumpSettings::UTF8MB4], message: 'Must be utf8 or utf8mb4')]
    public const DEFAULT_CHARACTER_SET = 'default-character-set';

    #[DefaultValue(value: true, description: 'Disable key checks during import')]
    public const DISABLE_KEYS = 'disable-keys';

    #[DefaultValue(value: true, description: 'Use extended INSERT syntax')]
    public const EXTENDED_INSERT = 'extended-insert';

    #[DefaultValue(value: false, description: 'Include events')]
    public const EVENTS = 'events';

    #[DefaultValue(value: true, description: 'Use hexadecimal notation for binary data')]
    public const HEX_BLOB = 'hex-blob';

    #[DefaultValue(value: false, description: 'Use INSERT IGNORE statements')]
    public const INSERT_IGNORE = 'insert-ignore';

    #[DefaultValue(value: false, description: 'Use REPLACE statements instead of INSERT')]
    public const REPLACE = 'replace';

    #[DefaultValue(value: 1000000, description: 'Network buffer length')]
    #[Constraint(min: 1024, message: 'Net buffer length must be at least 1024')]
    public const NET_BUFFER_LENGTH = 'net_buffer_length';

    #[DefaultValue(value: true, description: 'Disable autocommit during import')]
    public const NO_AUTOCOMMIT = 'no-autocommit';

    #[DefaultValue(value: false, description: 'Skip CREATE statements')]
    public const NO_CREATE_INFO = 'no-create-info';

    #[DefaultValue(value: true, description: 'Lock tables during dump')]
    public const LOCK_TABLES = 'lock-tables';

    #[DefaultValue(value: false, description: 'Include stored routines')]
    public const ROUTINES = 'routines';

    #[DefaultValue(value: true, description: 'Use single transaction')]
    public const SINGLE_TRANSACTION = 'single-transaction';

    #[DefaultValue(value: false, description: 'Skip triggers')]
    public const SKIP_TRIGGERS = 'skip-triggers';

    #[DefaultValue(value: false, description: 'Skip timezone UTC setting')]
    public const SKIP_TZ_UTC = 'skip-tz-utc';

    #[DefaultValue(value: false, description: 'Skip comments in output')]
    public const SKIP_COMMENTS = 'skip-comments';

    #[DefaultValue(value: false, description: 'Skip dump date in comments')]
    public const SKIP_DUMP_DATE = 'skip-dump-date';

    #[DefaultValue(value: false, description: 'Skip DEFINER clauses')]
    public const SKIP_DEFINER = 'skip-definer';

    #[DefaultValue(value: '', description: 'WHERE clause for filtering data')]
    public const WHERE = 'where';

    #[DefaultValue(value: true, description: 'Disable foreign key checks (deprecated)')]
    #[Deprecated(
        reason: 'This option is deprecated and may be removed in a future version',
        alternative: 'Use init_commands to set FOREIGN_KEY_CHECKS manually',
        since: '2.0'
    )]
    public const DISABLE_FOREIGN_KEYS_CHECK = 'disable-foreign-keys-check';
}
