<?php

namespace Druidfi\Mysqldump\Tests\Doubles;

use Druidfi\Mysqldump\TypeAdapter\TypeAdapterInterface;
use Druidfi\Mysqldump\DumpSettings;
use PDO;

class FakeTypeAdapter implements TypeAdapterInterface
{
    public function __construct(PDO $conn, DumpSettings $settings)
    {
        // Do nothing; test double
    }

    public function addDropDatabase(string $databaseName): string { return ''; }
    public function addDropTrigger(string $triggerName): string { return ''; }
    public function backupParameters(): string { return ''; }
    public function commitTransaction(): string { return ''; }
    public function createEvent(array $row): string { return ''; }
    public function createFunction(array $row): string { return ''; }
    public function createProcedure(array $row): string { return ''; }
    public function createTable(array $row): string { return ''; }
    public function createTrigger(array $row): string { return ''; }
    public function createView(array $row): string { return ''; }
    public function databases(string $databaseName): string { return ''; }
    public function dropTable(string $tableName): string { return ''; }
    public function dropView(string $viewName): string { return ''; }
    public function endAddDisableKeys(string $tableName): string { return ''; }
    public function endAddLockTable(string $tableName): string { return ''; }
    public function endDisableAutocommit(): string { return ''; }
    public function getDatabaseHeader(string $databaseName): string { return ''; }
    public function getVersion(): string { return 'fake'; }
    public function lockTable(string $tableName): void { }
    public function parseColumnType(array $colType): array { return []; }
    public function restoreParameters(): string { return ''; }
    public function setupTransaction(): string { return ''; }
    public function showColumns(string $tableName): string { return ''; }
    public function showCreateEvent(string $eventName): string { return ''; }
    public function showCreateFunction(string $functionName): string { return ''; }
    public function showCreateProcedure(string $procedureName): string { return ''; }
    public function showCreateTable(string $tableName): string { return ''; }
    public function showCreateTrigger(string $triggerName): string { return ''; }
    public function showCreateView(string $viewName): string { return ''; }
    public function showEvents(string $databaseName): string { return ''; }
    public function showFunctions(string $databaseName): string { return ''; }
    public function showProcedures(string $databaseName): string { return ''; }
    public function showTables(string $databaseName): string { return ''; }
    public function showTriggers(string $databaseName): string { return ''; }
    public function showViews(string $databaseName): string { return ''; }
    public function startAddDisableKeys(string $tableName): string { return ''; }
    public function startAddLockTable(string $tableName): string { return ''; }
    public function startDisableAutocommit(): string { return ''; }
    public function startTransaction(): string { return ''; }
    public function unlockTable(string $tableName): void { }
}
