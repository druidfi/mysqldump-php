<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump;

use Closure;
use Druidfi\Mysqldump\Exception\DumpException;
use Druidfi\Mysqldump\TypeAdapter\TypeAdapterInterface;
use PDO;

/**
 * Dumps the data rows of a single table as INSERT/REPLACE statements.
 *
 * Owns the SELECT statement building, value quoting and the surrounding
 * lock/disable-keys/autocommit statements for one table's data section.
 * Column type information is collected by Mysqldump while dumping table
 * structures and looked up through the getColumnTypes closure.
 */
class TableDataDumper
{
    /**
     * @param Closure $getColumnTypes function(string $table): array<string, array<string, mixed>>
     * @param Closure $getTableWhere function(string $table): string|false
     * @param Closure $getTableLimit function(string $table): int|string|false
     * @param Closure|null $transformTableRow function(string $table, array $row): array
     * @param Closure|null $transformColumnValue function(string $table, string $col, mixed $value, array $row): mixed
     * @param Closure|null $info function(string $object, array $payload): void
     */
    public function __construct(
        private readonly PDO $conn,
        private readonly DumpSettings $settings,
        private readonly TypeAdapterInterface $db,
        private readonly DumpWriter $writer,
        private readonly Closure $getColumnTypes,
        private readonly Closure $getTableWhere,
        private readonly Closure $getTableLimit,
        private readonly ?Closure $transformTableRow = null,
        private readonly ?Closure $transformColumnValue = null,
        private readonly ?Closure $info = null,
    ) {
    }

    /**
     * Table rows extractor.
     *
     * @param string $tableName Name of table to export
     * @throws DumpException
     */
    public function dump(string $tableName): void
    {
        $this->prepareListValues($tableName);

        $columnTypes = ($this->getColumnTypes)($tableName);

        // getting the column statement has side effect, so we backup this setting for consitency
        $completeInsertBackup = $this->settings->isEnabled('complete-insert');

        $query = $this->buildSelectQuery($tableName, $columnTypes);

        // colNames is used to get the name of the columns when using complete-insert;
        // resolved after buildSelectQuery() because getColumnStmt() enables
        // complete-insert when the table has virtual columns
        $colNames = $this->settings->isEnabled('complete-insert') ? $this->getColumnNames($columnTypes) : [];

        $count = $this->writeInsertStatements($tableName, $query, $columnTypes, $colNames);

        $this->endListValues($tableName, $count);

        if ($this->info !== null) {
            ($this->info)('table', ['name' => $tableName, 'completed' => true, 'rowCount' => $count]);
        }

        $this->settings->setCompleteInsert($completeInsertBackup);
    }

    /**
     * Build the SELECT statement used to read the table rows, applying the
     * per-table (or default) WHERE condition and LIMIT.
     *
     * @param string $tableName Name of table to export
     * @param array<string, array<string, mixed>> $columnTypes Column type info for the table
     */
    private function buildSelectQuery(string $tableName, array $columnTypes): string
    {
        // colStmt is used to form a query to obtain row values
        $colStmt = $this->getColumnStmt($columnTypes);

        $query = "SELECT " . implode(",", $colStmt) . " FROM `$tableName`";

        // Table specific conditions override the default 'where'
        $condition = ($this->getTableWhere)($tableName);

        if ($condition) {
            $query .= sprintf(' WHERE %s', $condition);
        }

        if ($limit = ($this->getTableLimit)($tableName)) {
            $query .= is_numeric($limit) ?
                sprintf(' LIMIT %d', $limit) :
                sprintf(' LIMIT %s', $limit);
        }

        return $query;
    }

    /**
     * Run the SELECT query and write the rows as INSERT/REPLACE statements,
     * flushing a statement whenever it exceeds net_buffer_length (or per row
     * when extended-insert is off). Reports progress through the info hook.
     *
     * @param string $tableName Name of table to export
     * @param string $query SELECT statement reading the rows
     * @param array<string, array<string, mixed>> $columnTypes Column type info for the table
     * @param string[] $colNames Quoted column names for complete-insert, empty otherwise
     * @return int Number of rows dumped
     * @throws DumpException
     */
    private function writeInsertStatements(string $tableName, string $query, array $columnTypes, array $colNames): int
    {
        $resultSet = $this->conn->query($query);
        $resultSet->setFetchMode(PDO::FETCH_ASSOC);

        $insertType = $this->getInsertType()->value;
        $count = 0;
        $onlyOnce = true;

        $isInfoCallable = $this->info !== null;
        if ($isInfoCallable) {
            ($this->info)('table', ['name' => $tableName, 'completed' => false, 'rowCount' => $count]);
        }

        $line = '';
        foreach ($resultSet as $row) {
            $count++;
            $values = $this->prepareColumnValues($tableName, $columnTypes, $row);
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
                $this->writer->write($line . ';' . PHP_EOL);
                $line = '';

                if ($isInfoCallable) {
                    ($this->info)('table', ['name' => $tableName, 'completed' => false, 'rowCount' => $count]);
                }
            }
        }

        $resultSet->closeCursor();

        if ($line !== '') {
            $this->writer->write($line. ';' . PHP_EOL);
        }

        return $count;
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
     * Table rows extractor, append information prior to dump.
     *
     * @param string $tableName Name of table to export
     * @throws DumpException
     */
    private function prepareListValues(string $tableName): void
    {
        if (!$this->settings->skipComments()) {
            $this->writer->write(
                "--" . PHP_EOL .
                "-- Dumping data for table `$tableName`" . PHP_EOL .
                "--" . PHP_EOL . PHP_EOL
            );
        }

        if ($this->settings->isEnabled('lock-tables') && !$this->settings->isEnabled('single-transaction')) {
            $this->db->lockTable($tableName);
        }

        if ($this->settings->isEnabled('add-locks')) {
            $this->writer->write($this->db->startAddLockTable($tableName));
        }

        if ($this->settings->isEnabled('disable-keys')) {
            $this->writer->write($this->db->startAddDisableKeys($tableName));
        }

        // Disable autocommit for faster reload
        if ($this->settings->isEnabled('no-autocommit')) {
            $this->writer->write($this->db->startDisableAutocommit());
        }
    }

    /**
     * Table rows extractor, close locks and commits after dump.
     *
     * @param string $tableName Name of table to export.
     * @param integer $count Number of rows inserted.
     * @throws DumpException
     */
    private function endListValues(string $tableName, int $count = 0): void
    {
        if ($this->settings->isEnabled('disable-keys')) {
            $this->writer->write($this->db->endAddDisableKeys($tableName));
        }

        if ($this->settings->isEnabled('add-locks')) {
            $this->writer->write($this->db->endAddLockTable($tableName));
        }

        if ($this->settings->isEnabled('lock-tables')
            && !$this->settings->isEnabled('single-transaction')) {
            $this->db->unlockTable($tableName);
        }

        // Commit to enable autocommit
        if ($this->settings->isEnabled('no-autocommit')) {
            $this->writer->write($this->db->endDisableAutocommit());
        }

        $this->writer->write(PHP_EOL);

        if (!$this->settings->skipComments()) {
            $this->writer->write(
                "-- Dumped table `" . $tableName . "` with $count row(s)" . PHP_EOL .
                '--' . PHP_EOL . PHP_EOL
            );
        }
    }

    /**
     * Prepare values for output.
     *
     * @param string $tableName Name of table which contains rows
     * @param array<string, array<string, mixed>> $columnTypes Column type info for the table
     * @param array<string, mixed> $row Associative array of column names and values to be quoted
     * @return array<int, mixed> quoted values ready for the VALUES list
     */
    private function prepareColumnValues(string $tableName, array $columnTypes, array $row): array
    {
        $ret = [];

        if ($this->transformTableRow) {
            $row = ($this->transformTableRow)($tableName, $row);
        }

        $hexBlobEnabled = $this->settings->isEnabled('hex-blob');
        foreach ($row as $colName => $colValue) {
            if ($this->transformColumnValue) {
                $colValue = ($this->transformColumnValue)($tableName, $colName, $colValue, $row);
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

            $ret[] = $this->conn->quote($colValue);
        }

        return $ret;
    }

    /**
     * Build SQL List of all columns on current table which will be used for selecting.
     *
     * @param array<string, array<string, mixed>> $columnTypes Column type info for the table
     * @return string[] SQL sentence with columns for select
     */
    private function getColumnStmt(array $columnTypes): array
    {
        $colStmt = [];
        foreach ($columnTypes as $colName => $colType) {
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
     * @param array<string, array<string, mixed>> $columnTypes Column type info for the table
     * @return string[] columns for sql sentence for insert
     */
    private function getColumnNames(array $columnTypes): array
    {
        $colNames = [];

        foreach ($columnTypes as $colName => $colType) {
            if ($colType['is_virtual']) {
                $this->settings->setCompleteInsert();
            } else {
                $colNames[] = sprintf('`%s`', $colName);
            }
        }

        return $colNames;
    }
}
