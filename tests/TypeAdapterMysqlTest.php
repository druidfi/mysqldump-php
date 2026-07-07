<?php

namespace Druidfi\Mysqldump\Tests;

use Druidfi\Mysqldump\DumpSettings;
use Druidfi\Mysqldump\TypeAdapter\TypeAdapterMysql;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypeAdapterMysql::class)]
class TypeAdapterMysqlTest extends TestCase
{
    private function createAdapter(): TypeAdapterMysql
    {
        // ERRMODE_SILENT: the constructor's MySQL init commands (SET NAMES ...)
        // are not valid SQLite and must not abort the test.
        $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);

        return new TypeAdapterMysql($pdo, new DumpSettings([]));
    }

    public function testQuoteIdentifierWrapsInBackticks(): void
    {
        $adapter = $this->createAdapter();

        $this->assertSame('`users`', $adapter->quoteIdentifier('users'));
    }

    public function testQuoteIdentifierEscapesEmbeddedBackticks(): void
    {
        $adapter = $this->createAdapter();

        $this->assertSame('`weird``name`', $adapter->quoteIdentifier('weird`name'));
        $this->assertSame('``` `', $adapter->quoteIdentifier('` '));
    }

    public function testShowCreateStatementsQuoteTheName(): void
    {
        $adapter = $this->createAdapter();

        $this->assertSame('SHOW CREATE TABLE `weird``name`', $adapter->showCreateTable('weird`name'));
        $this->assertSame('SHOW CREATE VIEW `v`', $adapter->showCreateView('v'));
    }

    public function testOutputStatementsQuoteTheName(): void
    {
        $adapter = $this->createAdapter();

        $this->assertSame(
            'DROP TABLE IF EXISTS `weird``name`;' . PHP_EOL,
            $adapter->dropTable('weird`name')
        );
        $this->assertSame(
            'LOCK TABLES `weird``name` WRITE;' . PHP_EOL,
            $adapter->startAddLockTable('weird`name')
        );
        $this->assertSame(
            '/*!40000 ALTER TABLE `weird``name` DISABLE KEYS */;' . PHP_EOL,
            $adapter->startAddDisableKeys('weird`name')
        );
    }

    public function testShowTablesQuotesDatabaseNameAsStringLiteral(): void
    {
        $adapter = $this->createAdapter();

        $this->assertStringContainsString("TABLE_SCHEMA='test'", $adapter->showTables('test'));
        // A quote in the database name must not break out of the string literal
        $this->assertStringContainsString("TABLE_SCHEMA='te''st'", $adapter->showTables("te'st"));
    }
}
