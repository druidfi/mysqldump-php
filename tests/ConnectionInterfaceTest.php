<?php

namespace Druidfi\Mysqldump\Tests;

use Druidfi\Mysqldump\ConnectionInterface;
use Druidfi\Mysqldump\DatabaseConnector;
use Druidfi\Mysqldump\Mysqldump;
use Druidfi\Mysqldump\Tests\Doubles\FakeConnection;
use Druidfi\Mysqldump\Tests\Doubles\SqliteTypeAdapter;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Mysqldump::class)]
#[CoversClass(DatabaseConnector::class)]
class ConnectionInterfaceTest extends TestCase
{
    private string $outputFile;

    protected function setUp(): void
    {
        $this->outputFile = tempnam(sys_get_temp_dir(), 'mysqldump-php-test');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }
    }

    public function testDatabaseConnectorImplementsConnectionInterface(): void
    {
        $connector = new DatabaseConnector('mysql:host=localhost;dbname=test', 'user', 'pass');
        $this->assertInstanceOf(ConnectionInterface::class, $connector);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function startDumpWithInjectedSqlite(FakeConnection $connection, array $settings = []): string
    {
        $dump = new Mysqldump('mysql:host=localhost;dbname=test', 'user', 'pass', $settings + [
            // BEGIN/COMMIT and trigger listing are MySQL-specific in the real adapter
            'single-transaction' => false,
            'skip-triggers' => true,
        ]);
        $dump->setConnector($connection);
        $dump->addTypeAdapter(SqliteTypeAdapter::class);
        $dump->start($this->outputFile);

        return file_get_contents($this->outputFile);
    }

    public function testStartUsesInjectedConnector(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER, name TEXT)');
        $pdo->exec("INSERT INTO users VALUES (1, 'John'), (2, 'Jane')");
        $connection = new FakeConnection($pdo);

        $output = $this->startDumpWithInjectedSqlite($connection, ['skip-comments' => true]);

        $this->assertSame(1, $connection->connectCalls);
        $this->assertStringContainsString('CREATE TABLE users (id INTEGER, name TEXT);', $output);
        $this->assertStringContainsString("INSERT INTO `users` VALUES (1,'John'),(2,'Jane');", $output);
    }

    public function testDumpFileHeaderUsesConnectorMetadata(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $connection = new FakeConnection($pdo, host: 'db.example.test', dbName: 'exampledb');

        $output = $this->startDumpWithInjectedSqlite($connection, ['skip-dump-date' => true]);

        $this->assertStringContainsString("Host: db.example.test\tDatabase: exampledb", $output);
    }
}
