<?php
declare(strict_types=1);

/**
 * PHP version of mysqldump cli that comes with MySQL.
 *
 * Tags: mysql mysqldump pdo php7 php8 database php sql mariadb mysql-backup.
 *
 * @category Library
 * @package  Druidfi\Mysqldump
 * @author   Marko Korhonen <marko.korhonen@druid.fi>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     https://github.com/druidfi/mysqldump-php
 * @see      https://github.com/ifsnop/mysqldump-php
 */

namespace Druidfi\Mysqldump;

use Closure;
use Druidfi\Mysqldump\TypeAdapter\TypeAdapterInterface;
use Druidfi\Mysqldump\TypeAdapter\TypeAdapterMysql;
use Druidfi\Mysqldump\ObjectDumper as ObjectDumper;
use Druidfi\Mysqldump\Exception\ConfigurationException;
use Druidfi\Mysqldump\Exception\ConnectionException;
use Druidfi\Mysqldump\Exception\DumpException;
use Druidfi\Mysqldump\Exception\MysqldumpException;
use PDO;

class Mysqldump
{
    // Database
    private ConnectionInterface $connector;
    private ?PDO $conn = null;
    private readonly DumpWriter $writer;
    private TypeAdapterInterface $db;

    /** @var class-string<TypeAdapterInterface> */
    private string $adapterClass = TypeAdapterMysql::class;

    private readonly DumpSettings $settings;
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $tableColumnTypes = [];
    private ?Closure $transformTableRowCallable = null;
    private ?Closure $transformColumnValueCallable = null;
    private ?Closure $infoCallable = null;

    /**
     * Keyed on table name, with the value as the conditions.
     * e.g. - 'users' => 'date_registered > NOW() - INTERVAL 6 MONTH'
     *
     * @var array<string, string>
     */
    private array $tableWheres = [];
    /** @var array<string, mixed> */
    private array $tableLimits = [];

    /**
     * Constructor of Mysqldump.
     *
     * @param string $dsn PDO DSN connection string
     * @param string|null $user SQL account username
     * @param string|null $pass SQL account password
     * @param array<string, mixed> $settings SQL database settings
     * @param array<int, mixed> $pdoOptions PDO configured attributes
     * @throws ConfigurationException
     */
    public function __construct(
        string  $dsn = '',
        ?string $user = null,
        ?string $pass = null,
        array   $settings = [],
        array   $pdoOptions = []
    )
    {
        $this->connector = new DatabaseConnector($dsn, $user, $pass, $pdoOptions);
        $this->settings = new DumpSettings($settings);
        $this->writer = new DumpWriter($this->settings);
    }


    /**
     * Connect with PDO using the configured connector.
     *
     * @throws ConnectionException
     */
    private function connect(): void
    {
        $this->conn = $this->connector->connect();
        $this->db = $this->getAdapter($this->conn);
    }

    private function write(string $data): int
    {
        return $this->writer->write($data);
    }

    /**
     * Format a comment header block, or an empty string when comments are skipped.
     *
     * @param string $text Single line of comment text
     */
    private function commentBlock(string $text): string
    {
        if ($this->settings->skipComments()) {
            return '';
        }

        return '--' . PHP_EOL . '-- ' . $text . PHP_EOL . '--' . PHP_EOL . PHP_EOL;
    }

    /**
     * Write a comment header block unless comments are skipped.
     *
     * @param string $text Single line of comment text
     */
    private function writeComment(string $text): void
    {
        $this->write($this->commentBlock($text));
    }

    /**
     * Run a SHOW CREATE statement and pass its first row to $writeCreate.
     *
     * @param string $query SHOW CREATE statement for the object
     * @param Closure $writeCreate function(array $row): void writes the create statement(s)
     */
    private function writeStructureFromShowCreate(string $query, Closure $writeCreate): void
    {
        $stmt = $this->conn->query($query);

        foreach ($stmt as $row) {
            $writeCreate($row);
            break;
        }

        $stmt->closeCursor();
    }

    /**
     * Primary function, triggers dumping.
     *
     * @param string|null $filename Name of file to write sql dump to
     * @throws MysqldumpException
     */
    public function start(?string $filename = ''): void
    {
        $destination = 'php://stdout';

        // Output file can be redefined here
        if (!empty($filename)) {
            $destination = $filename;
        }

        // Connect to database
        $this->connect();

        // Initialize the writer with the destination
        $this->writer->initialize($destination);

        // Write some basic info to output file
        if (!$this->settings->skipComments()) {
            $this->write($this->getDumpFileHeader());
        }

        // Initiate a transaction at global level to create a consistent snapshot.
        if ($this->settings->isEnabled('single-transaction')) {
            $this->conn->exec($this->db->setupTransaction());
            $this->conn->exec($this->db->startTransaction());
        }

        // Store server settings and use saner defaults to dump
        $this->write($this->db->backupParameters());

        if ($this->settings->isEnabled('databases')) {
            $this->write($this->db->getDatabaseHeader($this->connector->getDbName()));

            if ($this->settings->isEnabled('add-drop-database')) {
                $this->write($this->db->addDropDatabase($this->connector->getDbName()));
            }
        }

        $this->validateIncludedTables();

        if ($this->settings->isEnabled('databases')) {
            $this->write($this->db->databases($this->connector->getDbName()));
        }

        // Dumps the data rows of each table
        $dataDumper = new TableDataDumper(
            conn: $this->conn,
            settings: $this->settings,
            db: $this->db,
            writer: $this->writer,
            getColumnTypes: fn(string $table): array => $this->tableColumnTypes[$table],
            getTableWhere: $this->getTableWhere(...),
            getTableLimit: $this->getTableLimit(...),
            transformTableRow: $this->transformTableRowCallable,
            transformColumnValue: $this->transformColumnValueCallable,
            info: $this->infoCallable
        );

        // Use dedicated dumpers for different object types
        $tablesDumper = new ObjectDumper\TablesDumper(
            iterateTables: fn(): \Generator => $this->iterateTables(),
            matches: fn(string $name, array $arr): bool => $this->matches($name, $arr),
            getTableStructure: function (string $table): void { $this->getTableStructure($table); },
            listValues: function (string $table) use ($dataDumper): void {
                $no_data = $this->settings->isEnabled('no-data');
                if (!$no_data && !$this->matches($table, $this->settings->getNoData())) {
                    $dataDumper->dump($table);
                }
                // Column types are only needed between structure and data dump of this
                // table; free them so the map does not grow with the number of tables.
                unset($this->tableColumnTypes[$table]);
            },
            getExcludedTables: fn(): array => $this->settings->getExcludedTables(),
            getNoData: fn(): array => $this->settings->getNoData()
        );
        $tablesDumper->dump();

        $triggersDumper = new ObjectDumper\TriggersDumper(
            iterateTriggers: fn(): \Generator => $this->iterateTriggers(),
            getTriggerStructure: function (string $name): void { $this->getTriggerStructure($name); }
        );
        $triggersDumper->dump();

        $routinesDumper = new ObjectDumper\RoutinesDumper(
            iterateProcedures: fn(): \Generator => $this->iterateProcedures(),
            iterateFunctions: fn(): \Generator => $this->iterateFunctions(),
            getProcedureStructure: function (string $name): void { $this->getProcedureStructure($name); },
            getFunctionStructure: function (string $name): void { $this->getFunctionStructure($name); }
        );
        $routinesDumper->dump();

        $viewsDumper = new ObjectDumper\ViewsDumper(
            iterateViews: fn(): \Generator => $this->iterateViews(),
            matches: fn(string $name, array $arr): bool => $this->matches($name, $arr),
            getViewStructureTable: function (string $name): void {
                if ($this->settings->isEnabled('no-create-info')) { return; }
                if ($this->matches($name, $this->settings->getExcludedTables())) { return; }
                $this->tableColumnTypes[$name] = $this->getTableColumnTypes($name);
                $this->getViewStructureTable($name);
                // Only the Stand-In table above needs the view's column types.
                unset($this->tableColumnTypes[$name]);
            },
            getViewStructureView: function (string $name): void {
                if ($this->settings->isEnabled('no-create-info')) { return; }
                if ($this->matches($name, $this->settings->getExcludedTables())) { return; }
                $this->getViewStructureView($name);
            }
        );
        $viewsDumper->dump();

        $eventsDumper = new ObjectDumper\EventsDumper(
            iterateEvents: fn(): \Generator => $this->iterateEvents(),
            getEventStructure: function (string $name): void { $this->getEventStructure($name); }
        );
        $eventsDumper->dump();

        // Restore saved parameters.
        $this->write($this->db->restoreParameters());

        // End transaction.
        if ($this->settings->isEnabled('single-transaction')) {
            $this->conn->exec($this->db->commitTransaction());
        }

        // Write some stats to output file.
        if (!$this->settings->skipComments()) {
            $this->write($this->getDumpFileFooter());
        }

        // Close output file.
        $this->writer->close();
    }

    /**
     * Returns header for dump file.
     */
    private function getDumpFileHeader(): string
    {
        // Some info about software, source and time
        $header = sprintf(
            "-- mysqldump-php https://github.com/druidfi/mysqldump-php" . PHP_EOL .
            "--" . PHP_EOL .
            "-- Host: %s\tDatabase: %s" . PHP_EOL .
            "-- ------------------------------------------------------" . PHP_EOL,
            $this->connector->getHost(),
            $this->connector->getDbName()
        );

        if (!empty($version = $this->db->getVersion())) {
            $header .= "-- Server version \t" . $version . PHP_EOL;
        }

        if (!$this->settings->skipDumpDate()) {
            $header .= "-- Date: " . date('r') . PHP_EOL . PHP_EOL;
        }

        return $header;
    }

    /**
     * Returns footer for dump file.
     */
    private function getDumpFileFooter(): string
    {
        $footer = '-- Dump completed';

        if (!$this->settings->skipDumpDate()) {
            $footer .= ' on: ' . date('r');
        }

        $footer .= PHP_EOL;

        return $footer;
    }

    /**
     * Validate that all include-tables entries actually exist in the database.
     * This check will be removed once include-tables supports regexps.
     */
    private function validateIncludedTables(): void
    {
        $includedTables = $this->settings->getIncludedTables();

        if (empty($includedTables)) {
            return;
        }

        $existingTables = iterator_to_array($this->iterateObjectNames(
            $this->db->showTables($this->connector->getDbName())
        ));
        $missingTables = array_diff($includedTables, $existingTables);

        if (!empty($missingTables)) {
            throw new ConfigurationException(sprintf("Table '%s' not found in database", implode(',', $missingTables)));
        }
    }

    /**
     * Compare if $table name matches with a definition inside $arr.
     *
     * @param string[] $arr
     */
    private function matches(string $table, array $arr): bool
    {
        return in_array($table, $arr, true) || array_any(
            $arr,
            fn ($pattern): bool => is_string($pattern)
                && str_starts_with($pattern, '/')
                && preg_match($pattern, $table) === 1
        );
    }

    /**
     * Yield object names of one type from the database.
     *
     * Names are buffered and the cursor closed before yielding, so the connection
     * is free for the queries the consumer runs while dumping each object.
     *
     * @param string $query SHOW statement listing the objects
     * @param string|null $column Result column holding the name; null takes the first column
     * @param string[] $included When non-empty, only these names are yielded
     */
    private function iterateObjectNames(string $query, ?string $column = null, array $included = []): \Generator
    {
        $stmt = $this->conn->query($query);
        $names = [];

        foreach ($stmt as $row) {
            $name = $column === null ? current($row) : $row[$column];
            if (empty($included) || in_array($name, $included, true)) {
                $names[] = $name;
            }
        }

        $stmt->closeCursor();

        yield from $names;
    }

    private function iterateTables(): \Generator
    {
        yield from $this->iterateObjectNames(
            $this->db->showTables($this->connector->getDbName()),
            included: $this->settings->getIncludedTables()
        );
    }

    private function iterateViews(): \Generator
    {
        yield from $this->iterateObjectNames(
            $this->db->showViews($this->connector->getDbName()),
            included: $this->settings->getIncludedViews()
        );
    }

    private function iterateTriggers(): \Generator
    {
        if (!$this->settings->skipTriggers()) {
            yield from $this->iterateObjectNames($this->db->showTriggers($this->connector->getDbName()), 'Trigger');
        }
    }

    private function iterateProcedures(): \Generator
    {
        if ($this->settings->isEnabled('routines')) {
            yield from $this->iterateObjectNames(
                $this->db->showProcedures($this->connector->getDbName()),
                'procedure_name'
            );
        }
    }

    private function iterateFunctions(): \Generator
    {
        if ($this->settings->isEnabled('routines')) {
            yield from $this->iterateObjectNames(
                $this->db->showFunctions($this->connector->getDbName()),
                'function_name'
            );
        }
    }

    private function iterateEvents(): \Generator
    {
        if ($this->settings->isEnabled('events')) {
            yield from $this->iterateObjectNames($this->db->showEvents($this->connector->getDbName()), 'event_name');
        }
    }

    /**
     * Table structure extractor.
     *
     * @param string $tableName Name of table to export
     */
    private function getTableStructure(string $tableName): void
    {
        if (!$this->settings->isEnabled('no-create-info')) {
            // The comment is only written when the table exists, so it is
            // passed into the closure instead of being written up front.
            // Native mysqldump quotes identifiers in comment headers too.
            $comment = $this->commentBlock(
                sprintf('Table structure for table %s', $this->db->quoteIdentifier($tableName))
            );

            $this->writeStructureFromShowCreate(
                $this->db->showCreateTable($tableName),
                function (array $row) use ($tableName, $comment): void {
                    $this->write($comment);

                    if ($this->settings->isEnabled('add-drop-table')) {
                        $this->write($this->db->dropTable($tableName));
                    }

                    $this->write($this->db->createTable($row));
                }
            );
        }

        $this->tableColumnTypes[$tableName] = $this->getTableColumnTypes($tableName);
    }

    /**
     * Store column types to create data dumps and for Stand-In tables.
     *
     * @param string $tableName Name of table to export
     * @return array<string, array<string, mixed>> type column types detailed
     */
    private function getTableColumnTypes(string $tableName): array
    {
        $columnTypes = [];
        $columns = $this->conn->query($this->db->showColumns($tableName));
        $columns->setFetchMode(PDO::FETCH_ASSOC);

        foreach ($columns as $col) {
            $types = $this->db->parseColumnType($col);
            $columnTypes[$col['Field']] = [
                'is_numeric' => $types['is_numeric'],
                'is_blob' => $types['is_blob'],
                'type' => $types['type'],
                'type_sql' => $col['Type'],
                'is_virtual' => $types['is_virtual']
            ];
        }
        $columns->closeCursor();

        return $columnTypes;
    }

    /**
     * View structure extractor, create table (avoids cyclic references).
     *
     * @param string $viewName Name of view to export
     */
    private function getViewStructureTable(string $viewName): void
    {
        $this->writeComment(sprintf('Stand-In structure for view %s', $this->db->quoteIdentifier($viewName)));

        // create views as tables, to resolve dependencies
        $this->writeStructureFromShowCreate(
            $this->db->showCreateView($viewName),
            function (array $row) use ($viewName): void {
                if ($this->settings->isEnabled('add-drop-table')) {
                    $this->write($this->db->dropView($viewName));
                }

                $this->write($this->createStandInTable($viewName));
            }
        );
    }

    /**
     * Write a create table statement for the table Stand-In, show create
     * table would return a create algorithm when used on a view.
     *
     * @param string $viewName Name of view to export
     * @return string create statement
     */
    private function createStandInTable(string $viewName): string
    {
        $ret = [];

        foreach ($this->tableColumnTypes[$viewName] as $k => $v) {
            $ret[] = sprintf('%s %s', $this->db->quoteIdentifier((string) $k), $v['type_sql']);
        }

        $ret = implode(PHP_EOL . ',', $ret);

        return sprintf(
            "CREATE TABLE IF NOT EXISTS %s (" . PHP_EOL . "%s" . PHP_EOL . ");" . PHP_EOL,
            $this->db->quoteIdentifier($viewName),
            $ret
        );
    }

    /**
     * View structure extractor, create view.
     */
    private function getViewStructureView(string $viewName): void
    {
        $this->writeComment(sprintf('View structure for view %s', $this->db->quoteIdentifier($viewName)));

        // Create views, to resolve dependencies replacing tables with views
        $this->writeStructureFromShowCreate(
            $this->db->showCreateView($viewName),
            function (array $row) use ($viewName): void {
                // Because we must replace table with view, we should delete it
                $this->write($this->db->dropView($viewName));
                $this->write($this->db->createView($row));
            }
        );
    }

    /**
     * Trigger structure extractor.
     *
     * @param string $triggerName Name of trigger to export
     */
    private function getTriggerStructure(string $triggerName): void
    {
        $this->writeStructureFromShowCreate(
            $this->db->showCreateTrigger($triggerName),
            function (array $row) use ($triggerName): void {
                if ($this->settings->isEnabled('add-drop-trigger')) {
                    $this->write($this->db->addDropTrigger($triggerName));
                }

                $this->write($this->db->createTrigger($row));
            }
        );
    }

    /**
     * Procedure structure extractor.
     *
     * @param string $procedureName Name of procedure to export
     */
    private function getProcedureStructure(string $procedureName): void
    {
        $this->writeComment("Dumping routines for database '" . $this->connector->getDbName() . "'");

        $this->writeStructureFromShowCreate(
            $this->db->showCreateProcedure($procedureName),
            fn (array $row): int => $this->write($this->db->createProcedure($row))
        );
    }

    /**
     * Function structure extractor.
     *
     * @param string $functionName Name of function to export
     */
    private function getFunctionStructure(string $functionName): void
    {
        $this->writeComment("Dumping routines for database '" . $this->connector->getDbName() . "'");

        $this->writeStructureFromShowCreate(
            $this->db->showCreateFunction($functionName),
            fn (array $row): int => $this->write($this->db->createFunction($row))
        );
    }

    /**
     * Event structure extractor.
     *
     * @param string $eventName Name of event to export
     * @throws DumpException
     */
    private function getEventStructure(string $eventName): void
    {
        $this->writeComment("Dumping events for database '" . $this->connector->getDbName() . "'");

        $this->writeStructureFromShowCreate(
            $this->db->showCreateEvent($eventName),
            fn (array $row): int => $this->write($this->db->createEvent($row))
        );
    }

    /**
     * Keyed by table name, with the value as the conditions:
     * e.g. 'users' => 'date_registered > NOW() - INTERVAL 6 MONTH AND deleted=0'
     *
     * The conditions are inserted into the SELECT statements as raw SQL by
     * design and must not contain untrusted input.
     *
     * @param array<string, string> $tableWheres
     */
    public function setTableWheres(array $tableWheres): void
    {
        $this->tableWheres = $tableWheres;
    }

    public function getTableWhere(string $tableName): string|false
    {
        if (!empty($this->tableWheres[$tableName])) {
            return $this->tableWheres[$tableName];
        } elseif ($this->settings->getWhere()) {
            return $this->settings->getWhere();
        }

        return false;
    }

    /**
     * Keyed by table name, with the value as the numeric limit: e.g. 'users' => 3000
     *
     * Non-numeric values are ignored (see getTableLimit()), but the resulting
     * LIMIT is inserted into the SELECT statements as raw SQL by design and
     * must not contain untrusted input.
     *
     * @param array<string, mixed> $tableLimits
     */
    public function setTableLimits(array $tableLimits): void
    {
        $this->tableLimits = $tableLimits;
    }

    /**
     * Returns the LIMIT for the table. Must be numeric to be returned.
     */
    public function getTableLimit(string $tableName): int|string|false
    {
        if (!isset($this->tableLimits[$tableName])) {
            return false;
        }

        $limit = false;

        if (is_numeric($this->tableLimits[$tableName])) {
            $limit = $this->tableLimits[$tableName];
        }

        if (is_array($this->tableLimits[$tableName]) &&
            count($this->tableLimits[$tableName]) === 2 &&
            is_numeric(implode('', $this->tableLimits[$tableName]))
        ) {
            $limit = implode(',', $this->tableLimits[$tableName]);
        }

        return $limit;
    }

    /**
     * Replace the default DatabaseConnector, e.g. to supply an existing PDO
     * connection or a custom connection strategy. Must be called before start().
     */
    public function setConnector(ConnectionInterface $connector): void
    {
        $this->connector = $connector;
    }

    /**
     * Set the TypeAdapter class used by this instance.
     *
     * @param class-string $adapterClassName Must implement TypeAdapterInterface (validated at runtime)
     * @throws ConfigurationException
     */
    public function addTypeAdapter(string $adapterClassName): void
    {
        if (!is_a($adapterClassName, TypeAdapterInterface::class, true)) {
            $message = sprintf('Adapter %s is not instance of %s', $adapterClassName, TypeAdapterInterface::class);
            throw new ConfigurationException($message);
        }

        $this->adapterClass = $adapterClassName;
    }

    public function getAdapter(PDO $conn): TypeAdapterInterface
    {
        return new ($this->adapterClass)($conn, $this->settings);
    }

    /**
     * Set a callable that will be used to transform table rows.
     */
    public function setTransformTableRowHook(callable $callable): void
    {
        $this->transformTableRowCallable = $callable(...);
    }

    /**
     * Set a callable that will be used to report dump information.
     */
    public function setInfoHook(callable $callable): void
    {
        $this->infoCallable = $callable(...);
    }

    /**
     * Set a callable that will be used to transform column values.
     */
    public function setTransformColumnValueHook(callable $callable): void
    {
        $this->transformColumnValueCallable = $callable(...);
    }
}
