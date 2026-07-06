<?php

namespace Druidfi\Mysqldump\Tests\Doubles;

/**
 * FakeTypeAdapter variant whose SHOW-statement equivalents are valid SQLite,
 * so a full Mysqldump::start() run can execute against an in-memory SQLite
 * connection injected via ConnectionInterface.
 */
class SqliteTypeAdapter extends FakeTypeAdapter
{
    #[\Override]
    public function showTables(string $databaseName): string
    {
        return "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'";
    }

    #[\Override]
    public function showViews(string $databaseName): string
    {
        return "SELECT name FROM sqlite_master WHERE type = 'view'";
    }

    #[\Override]
    public function showCreateTable(string $tableName): string
    {
        return sprintf(
            "SELECT sql AS 'Create Table' FROM sqlite_master WHERE type = 'table' AND name = '%s'",
            $tableName
        );
    }

    #[\Override]
    public function createTable(array $row): string
    {
        return $row['Create Table'] . ';' . PHP_EOL;
    }

    #[\Override]
    public function showColumns(string $tableName): string
    {
        return sprintf("SELECT name AS Field, type AS Type FROM pragma_table_info('%s')", $tableName);
    }

    #[\Override]
    public function parseColumnType(array $colType): array
    {
        $type = strtolower((string) $colType['Type']);

        return [
            'is_numeric' => str_contains($type, 'int'),
            'is_blob' => false,
            'type' => $type,
            'is_virtual' => false,
        ];
    }
}
