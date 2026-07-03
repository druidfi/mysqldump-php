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
use Exception;
use PDO;

class Mysqldump
{
    // Database
    private readonly DatabaseConnector $connector;
    private ?PDO $conn = null;
    private readonly DumpWriter $writer;
    private TypeAdapterInterface $db;

    /** @var class-string<TypeAdapterInterface> */
    private string $adapterClass = TypeAdapterMysql::class;

    private readonly DumpSettings $settings;
    private array $tableColumnTypes = [];
    private ?Closure $transformTableRowCallable = null;
    private ?Closure $transformColumnValueCallable = null;
    private ?Closure $infoCallable = null;

    /**
     * Keyed on table name, with the value as the conditions.
     * e.g. - 'users' => 'date_registered > NOW() - INTERVAL 6 MONTH'
     */
    private array $tableWheres = [];
    private array $tableLimits = [];

    /**
     * Constructor of Mysqldump.
     *
     * @param string $dsn PDO DSN connection string
     * @param string|null $user SQL account username
     * @param string|null $pass SQL account password
     * @param array $settings SQL database settings
     * @param array $pdoOptions PDO configured attributes
     * @throws Exception
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
     * Connect with PDO using the DatabaseConnector.
     *
     * @throws Exception
     */
    private function connect(): void
    {
        $this->conn = $this->connector->connect();
        $this->db = $this->getAdapter($this->conn);
    }

    public function getAdapter(PDO $conn): TypeAdapterInterface
    {
        return new ($this->adapterClass)($conn, $this->settings);
    }

    private function write(string $data): int
    {
        return $this->writer->write($data);
    }

    private function getInsertType(): InsertType
    {
        if ($this->settings->isEnabled('replace')) {
            return InsertType::Replace;
        }

        if ($this->settings->isEnabled('insert-ignore')) {
            return InsertType::InsertIgnore;
        }

        return InsertType::Insert;
    }

    /**
     * Primary function, triggers dumping.
     *
     * @param string|null $filename Name of file to write sql dump to
     * @throws Exception
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

        // Use dedicated dumpers for different object types
        $tablesDumper = new ObjectDumper\TablesDumper(
            iterateTables: fn(): \Generator => $this->iterateTables(),
            matches: fn(string $name, array $arr): bool => $this->matches($name, $arr),
            getTableStructure: function (string $table): void { $this->getTableStructure($table); },
            listValues: function (string $table): void {
                $no_data = $this->settings->isEnabled('no-data');
                if (!$no_data && !$this->matches($table, $this->settings->getNoData())) {
                    $this->listValues($table);
                }
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
            throw new Exception(sprintf("Table '%s' not found in database", implode(',', $missingTables)));
        }
    }

    /**
     * Compare if $table name matches with a definition inside $arr.
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
     * @param array $included When non-empty, only these names are yielded
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
            $ret = '';

            if (!$this->settings->skipComments()) {
                $ret = sprintf(
                    "--" . PHP_EOL .
                    "-- Table structure for table `%s`" . PHP_EOL .
                    "--" . PHP_EOL . PHP_EOL,
                    $tableName
                );
            }

            $stmt = $this->db->showCreateTable($tableName);

            $stmtCT = $this->conn->query($stmt);
            foreach ($stmtCT as $r) {
                $this->write($ret);

                if ($this->settings->isEnabled('add-drop-table')) {
                    $this->write($this->db->dropTable($tableName));
                }

                $this->write($this->db->createTable($r));

                break;
            }
            $stmtCT->closeCursor();
        }

        $this->tableColumnTypes[$tableName] = $this->getTableColumnTypes($tableName);
    }

    /**
     * Store column types to create data dumps and for Stand-In tables.
     *
     * @param string $tableName Name of table to export
     * @return array type column types detailed
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
        if (!$this->settings->skipComments()) {
            $ret = (
                '--' . PHP_EOL .
                sprintf('-- Stand-In structure for view `%s`', $viewName) . PHP_EOL .
                '--' . PHP_EOL . PHP_EOL
            );

            $this->write($ret);
        }

        $stmt = $this->db->showCreateView($viewName);

        // create views as tables, to resolve dependencies
        $stmtSCV = $this->conn->query($stmt);
        foreach ($stmtSCV as $r) {
            if ($this->settings->isEnabled('add-drop-table')) {
                $this->write($this->db->dropView($viewName));
            }

            $this->write($this->createStandInTable($viewName));

            break;
        }
        $stmtSCV->closeCursor();
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
            $ret[] = sprintf('`%s` %s', $k, $v['type_sql']);
        }

        $ret = implode(PHP_EOL . ',', $ret);

        return sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (" . PHP_EOL . "%s" . PHP_EOL . ");" . PHP_EOL,
            $viewName,
            $ret
        );
    }

    /**
     * View structure extractor, create view.
     */
    private function getViewStructureView(string $viewName): void
    {
        if (!$this->settings->skipComments()) {
            $ret = sprintf(
                "--" . PHP_EOL .
                "-- View structure for view `%s`" . PHP_EOL .
                "--" . PHP_EOL . PHP_EOL,
                $viewName
            );

            $this->write($ret);
        }

        $stmt = $this->db->showCreateView($viewName);

        // Create views, to resolve dependencies replacing tables with views
        $stmtSCV2 = $this->conn->query($stmt);
        foreach ($stmtSCV2 as $r) {
            // Because we must replace table with view, we should delete it
            $this->write($this->db->dropView($viewName));
            $this->write($this->db->createView($r));

            break;
        }
        $stmtSCV2->closeCursor();
    }

    /**
     * Trigger structure extractor.
     *
     * @param string $triggerName Name of trigger to export
     */
    private function getTriggerStructure(string $triggerName): void
    {
        $stmt = $this->db->showCreateTrigger($triggerName);

        $stmtSCT = $this->conn->query($stmt);
        foreach ($stmtSCT as $r) {
            if ($this->settings->isEnabled('add-drop-trigger')) {
                $this->write($this->db->addDropTrigger($triggerName));
            }

            $this->write($this->db->createTrigger($r));

            $stmtSCT->closeCursor();
            return;
        }
        $stmtSCT->closeCursor();
    }

    /**
     * Procedure structure extractor.
     *
     * @param string $procedureName Name of procedure to export
     */
    private function getProcedureStructure(string $procedureName): void
    {
        if (!$this->settings->skipComments()) {
            $ret = "--" . PHP_EOL .
                "-- Dumping routines for database '" . $this->connector->getDbName() . "'" . PHP_EOL .
                "--" . PHP_EOL . PHP_EOL;
            $this->write($ret);
        }

        $stmt = $this->db->showCreateProcedure($procedureName);

        $stmtSCP = $this->conn->query($stmt);
        foreach ($stmtSCP as $r) {
            $this->write($this->db->createProcedure($r));
            $stmtSCP->closeCursor();
            return;
        }
        $stmtSCP->closeCursor();
    }

    /**
     * Function structure extractor.
     *
     * @param string $functionName Name of function to export
     */
    private function getFunctionStructure(string $functionName): void
    {
        if (!$this->settings->skipComments()) {
            $ret = "--" . PHP_EOL .
                "-- Dumping routines for database '" . $this->connector->getDbName() . "'" . PHP_EOL .
                "--" . PHP_EOL . PHP_EOL;
            $this->write($ret);
        }

        $stmt = $this->db->showCreateFunction($functionName);

        $stmtSCF = $this->conn->query($stmt);
        foreach ($stmtSCF as $r) {
            $this->write($this->db->createFunction($r));
            $stmtSCF->closeCursor();
            return;
        }
        $stmtSCF->closeCursor();
    }

    /**
     * Event structure extractor.
     *
     * @param string $eventName Name of event to export
     * @throws Exception
     */
    private function getEventStructure(string $eventName): void
    {
        if (!$this->settings->skipComments()) {
            $ret = "--" . PHP_EOL .
                "-- Dumping events for database '" . $this->connector->getDbName() . "'" . PHP_EOL .
                "--" . PHP_EOL . PHP_EOL;
            $this->write($ret);
        }

        $stmt = $this->db->showCreateEvent($eventName);

        $stmtSCE = $this->conn->query($stmt);
        foreach ($stmtSCE as $r) {
            $this->write($this->db->createEvent($r));
            $stmtSCE->closeCursor();
            return;
        }
        $stmtSCE->closeCursor();
    }

    /**
     * Prepare values for output.
     *
     * @param string $tableName Name of table which contains rows
     * @param array $row Associative array of column names and values to be quoted
     */
    private function prepareColumnValues(string $tableName, array $row): array
    {
        $ret = [];
        $columnTypes = $this->tableColumnTypes[$tableName];

        if ($this->transformTableRowCallable) {
            $row = ($this->transformTableRowCallable)($tableName, $row);
        }

        $dbHandler = $this->conn;
        $hexBlobEnabled = $this->settings->isEnabled('hex-blob');
        foreach ($row as $colName => $colValue) {
            if ($this->transformColumnValueCallable) {
                $colValue = ($this->transformColumnValueCallable)($tableName, $colName, $colValue, $row);
            }

            if ($colValue === null) {
                $ret[] = "NULL";
                continue;
            }

            $colType = $columnTypes[$colName];
            if ($hexBlobEnabled && $colType['is_blob']) {
                if ($colType['type'] == 'bit' || $colValue !== '') {
                    $ret[] = sprintf('0x%s', $colValue);
                } else {
                    $ret[] = "''";
                }
                continue;
            }

            if ($colType['is_numeric']) {
                $ret[] = $colValue;
                continue;
            }

            $ret[] = $dbHandler->quote($colValue);
        }

        return $ret;
    }

    /**
     * Table rows extractor.
     *
     * @param string $tableName Name of table to export
     */
    private function listValues(string $tableName): void
    {
        $this->prepareListValues($tableName);

        $onlyOnce = true;
        $lineSize = 0;
        $colNames = [];

        // getting the column statement has side effect, so we backup this setting for consitency
        $completeInsertBackup = $this->settings->isEnabled('complete-insert');

        // colStmt is used to form a query to obtain row values
        $colStmt = $this->getColumnStmt($tableName);

        // colNames is used to get the name of the columns when using complete-insert
        if ($this->settings->isEnabled('complete-insert')) {
            $colNames = $this->getColumnNames($tableName);
        }

        $stmt = "SELECT " . implode(",", $colStmt) . " FROM `$tableName`";

        // Table specific conditions override the default 'where'
        $condition = $this->getTableWhere($tableName);

        if ($condition) {
            $stmt .= sprintf(' WHERE %s', $condition);
        }

        if ($limit = $this->getTableLimit($tableName)) {
            $stmt .= is_numeric($limit) ?
                sprintf(' LIMIT %d', $limit) :
                sprintf(' LIMIT %s', $limit);
        }

        $resultSet = $this->conn->query($stmt);
        $resultSet->setFetchMode(PDO::FETCH_ASSOC);

        $insertType = $this->getInsertType()->value;
        $count = 0;

        $isInfoCallable = $this->infoCallable !== null;
        if ($isInfoCallable) {
            ($this->infoCallable)('table', ['name' => $tableName, 'completed' => false, 'rowCount' => $count]);
        }

        $line = '';
        foreach ($resultSet as $row) {
            $count++;
            $values = $this->prepareColumnValues($tableName, $row);
            $valueList = implode(',', $values);

            if ($onlyOnce || !$this->settings->isEnabled('extended-insert')) {
                if ($this->settings->isEnabled('complete-insert') && count($colNames)) {
                    $line .= sprintf(
                        '%s INTO `%s` (%s) VALUES (%s)',
                        $insertType,
                        $tableName,
                        implode(', ', $colNames),
                        $valueList
                    );
                } else {
                    $line .= sprintf('%s INTO `%s` VALUES (%s)', $insertType, $tableName, $valueList);
                }
                $onlyOnce = false;
            } else {
                $line .= sprintf(',(%s)', $valueList);
            }

            if ((strlen($line) > $this->settings->getNetBufferLength())
                || !$this->settings->isEnabled('extended-insert')) {
                $onlyOnce = true;
                $this->write($line . ';' . PHP_EOL);
                $line = '';

                if ($isInfoCallable) {
                    ($this->infoCallable)('table', ['name' => $tableName, 'completed' => false, 'rowCount' => $count]);
                }
            }
        }

        $resultSet->closeCursor();

        if ($line !== '') {
            $this->write($line. ';' . PHP_EOL);
        }

        $this->endListValues($tableName, $count);

        if ($isInfoCallable) {
            ($this->infoCallable)('table', ['name' => $tableName, 'completed' => true, 'rowCount' => $count]);
        }

        $this->settings->setCompleteInsert($completeInsertBackup);
    }

    /**
     * Table rows extractor, append information prior to dump.
     *
     * @param string $tableName Name of table to export
     */
    private function prepareListValues(string $tableName): void
    {
        if (!$this->settings->skipComments()) {
            $this->write(
                "--" . PHP_EOL .
                "-- Dumping data for table `$tableName`" . PHP_EOL .
                "--" . PHP_EOL . PHP_EOL
            );
        }

        if ($this->settings->isEnabled('lock-tables') && !$this->settings->isEnabled('single-transaction')) {
            $this->db->lockTable($tableName);
        }

        if ($this->settings->isEnabled('add-locks')) {
            $this->write($this->db->startAddLockTable($tableName));
        }

        if ($this->settings->isEnabled('disable-keys')) {
            $this->write($this->db->startAddDisableKeys($tableName));
        }

        // Disable autocommit for faster reload
        if ($this->settings->isEnabled('no-autocommit')) {
            $this->write($this->db->startDisableAutocommit());
        }
    }

    /**
     * Table rows extractor, close locks and commits after dump.
     *
     * @param string $tableName Name of table to export.
     * @param integer $count Number of rows inserted.
     */
    private function endListValues(string $tableName, int $count = 0): void
    {
        if ($this->settings->isEnabled('disable-keys')) {
            $this->write($this->db->endAddDisableKeys($tableName));
        }

        if ($this->settings->isEnabled('add-locks')) {
            $this->write($this->db->endAddLockTable($tableName));
        }

        if ($this->settings->isEnabled('lock-tables')
            && !$this->settings->isEnabled('single-transaction')) {
            $this->db->unlockTable($tableName);
        }

        // Commit to enable autocommit
        if ($this->settings->isEnabled('no-autocommit')) {
            $this->write($this->db->endDisableAutocommit());
        }

        $this->write(PHP_EOL);

        if (!$this->settings->skipComments()) {
            $this->write(
                "-- Dumped table `" . $tableName . "` with $count row(s)" . PHP_EOL .
                '--' . PHP_EOL . PHP_EOL
            );
        }
    }

    /**
     * Build SQL List of all columns on current table which will be used for selecting.
     *
     * @param string $tableName Name of table to get columns
     *
     * @return array SQL sentence with columns for select
     */
    protected function getColumnStmt(string $tableName): array
    {
        $colStmt = [];
        foreach ($this->tableColumnTypes[$tableName] as $colName => $colType) {
            if ($colType['is_virtual']) {
                $this->settings->setCompleteInsert();
            } elseif ($colType['type'] == 'double') {
                // PHP 8.1+ returns double fields with float precision issues; dump via CONCAT
                $colStmt[] = sprintf("CONCAT(`%s`) AS `%s`", $colName, $colName);
            } elseif ($colType['type'] === 'bit' && $this->settings->isEnabled('hex-blob')) {
                $colStmt[] = sprintf("LPAD(HEX(`%s`),2,'0') AS `%s`", $colName, $colName);
            } elseif ($colType['is_blob'] && $this->settings->isEnabled('hex-blob')) {
                $colStmt[] = sprintf("HEX(`%s`) AS `%s`", $colName, $colName);
            } else {
                $colStmt[] = sprintf("`%s`", $colName);
            }
        }

        return $colStmt;
    }

    /**
     * Build SQL List of all columns on current table which will be used for inserting.
     *
     * @param string $tableName Name of table to get columns
     *
     * @return array columns for sql sentence for insert
     */
    private function getColumnNames(string $tableName): array
    {
        $colNames = [];

        foreach ($this->tableColumnTypes[$tableName] as $colName => $colType) {
            if ($colType['is_virtual']) {
                $this->settings->setCompleteInsert();
            } else {
                $colNames[] = sprintf('`%s`', $colName);
            }
        }

        return $colNames;
    }

    /**
     * Get table column types.
     */
    protected function tableColumnTypes(): array
    {
        return $this->tableColumnTypes;
    }

    /**
     * Keyed by table name, with the value as the conditions:
     * e.g. 'users' => 'date_registered > NOW() - INTERVAL 6 MONTH AND deleted=0'
     */
    public function setTableWheres(array $tableWheres): void
    {
        $this->tableWheres = $tableWheres;
    }

    public function getTableWhere(string $tableName): string|false
    {
        if (!empty($this->tableWheres[$tableName])) {
            return $this->tableWheres[$tableName];
        } elseif ($this->settings->get('where')) {
            return $this->settings->get('where');
        }

        return false;
    }

    /**
     * Keyed by table name, with the value as the numeric limit: e.g. 'users' => 3000
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
     * Set the TypeAdapter class used by this instance.
     *
     * @param class-string $adapterClassName Must implement TypeAdapterInterface (validated at runtime)
     * @throws Exception
     */
    public function addTypeAdapter(string $adapterClassName): void
    {
        if (!is_a($adapterClassName, TypeAdapterInterface::class, true)) {
            $message = sprintf('Adapter %s is not instance of %s', $adapterClassName, TypeAdapterInterface::class);
            throw new Exception($message);
        }

        $this->adapterClass = $adapterClassName;
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
