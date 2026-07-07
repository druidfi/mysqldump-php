<?php

namespace Druidfi\Mysqldump\Tests;

use Druidfi\Mysqldump\DumpSettings;
use Druidfi\Mysqldump\DumpWriter;
use Druidfi\Mysqldump\TableDataDumper;
use Druidfi\Mysqldump\Tests\Doubles\FakeTypeAdapter;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableDataDumper::class)]
class TableDataDumperTest extends TestCase
{
    private PDO $pdo;
    private string $outputFile;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->exec('CREATE TABLE users (id INTEGER, name TEXT)');
        $this->pdo->exec("INSERT INTO users VALUES (1, 'John'), (2, 'O''Hara')");
        $this->outputFile = tempnam(sys_get_temp_dir(), 'mysqldump-php-test');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->outputFile)) {
            unlink($this->outputFile);
        }
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $overrides
     */
    private function dumpUsersTable(array $settings = [], array $overrides = []): string
    {
        $dumpSettings = new DumpSettings($settings + ['skip-comments' => true]);
        $writer = new DumpWriter($dumpSettings);
        $writer->initialize($this->outputFile);

        $columnTypes = [
            'id' => ['is_numeric' => true, 'is_blob' => false, 'type' => 'int', 'type_sql' => 'int', 'is_virtual' => false],
            'name' => ['is_numeric' => false, 'is_blob' => false, 'type' => 'text', 'type_sql' => 'text', 'is_virtual' => false],
        ];

        $dumper = new TableDataDumper(
            conn: $this->pdo,
            settings: $dumpSettings,
            db: new FakeTypeAdapter($this->pdo, $dumpSettings),
            writer: $writer,
            getColumnTypes: fn(string $table): array => $columnTypes,
            getTableWhere: $overrides['getTableWhere'] ?? fn(string $table): false => false,
            getTableLimit: $overrides['getTableLimit'] ?? fn(string $table): false => false,
            transformTableRow: $overrides['transformTableRow'] ?? null,
            transformColumnValue: $overrides['transformColumnValue'] ?? null,
            info: $overrides['info'] ?? null,
        );

        $dumper->dump('users');
        $writer->close();

        return file_get_contents($this->outputFile);
    }

    public function testDumpsRowsAsExtendedInsert(): void
    {
        $output = $this->dumpUsersTable();

        $this->assertStringContainsString("INSERT INTO `users` VALUES (1,'John'),(2,'O''Hara');", $output);
    }

    public function testReplaceSettingProducesReplaceStatements(): void
    {
        $output = $this->dumpUsersTable(['replace' => true]);

        $this->assertStringContainsString('REPLACE INTO `users` VALUES', $output);
        $this->assertStringNotContainsString('INSERT INTO', $output);
    }

    public function testInsertIgnoreSettingProducesInsertIgnoreStatements(): void
    {
        $output = $this->dumpUsersTable(['insert-ignore' => true]);

        // Native mysqldump writes a double space in INSERT  IGNORE.
        $this->assertStringContainsString('INSERT  IGNORE INTO `users` VALUES', $output);
    }

    public function testCompleteInsertIncludesColumnNames(): void
    {
        $output = $this->dumpUsersTable(['complete-insert' => true]);

        $this->assertStringContainsString('INSERT INTO `users` (`id`, `name`) VALUES', $output);
    }

    public function testExtendedInsertDisabledWritesOneStatementPerRow(): void
    {
        $output = $this->dumpUsersTable(['extended-insert' => false]);

        $this->assertStringContainsString("INSERT INTO `users` VALUES (1,'John');", $output);
        $this->assertStringContainsString("INSERT INTO `users` VALUES (2,'O''Hara');", $output);
    }

    public function testTableWhereFiltersRows(): void
    {
        $output = $this->dumpUsersTable(overrides: [
            'getTableWhere' => fn(string $table): string => 'id = 1',
        ]);

        $this->assertStringContainsString("(1,'John')", $output);
        $this->assertStringNotContainsString("O''Hara", $output);
    }

    public function testTableLimitLimitsRows(): void
    {
        $output = $this->dumpUsersTable(overrides: [
            'getTableLimit' => fn(string $table): int => 1,
        ]);

        $this->assertStringContainsString("(1,'John')", $output);
        $this->assertStringNotContainsString("O''Hara", $output);
    }

    public function testTransformColumnValueHookIsApplied(): void
    {
        $output = $this->dumpUsersTable(overrides: [
            'transformColumnValue' => fn(string $table, string $col, mixed $value, array $row): mixed =>
                $col === 'name' ? 'anonymous' : $value,
        ]);

        $this->assertStringContainsString("(1,'anonymous'),(2,'anonymous')", $output);
        $this->assertStringNotContainsString('John', $output);
    }

    public function testTransformTableRowHookIsApplied(): void
    {
        $output = $this->dumpUsersTable(overrides: [
            'transformTableRow' => fn(string $table, array $row): array =>
                ['id' => $row['id'], 'name' => strtoupper((string) $row['name'])],
        ]);

        $this->assertStringContainsString("(1,'JOHN')", $output);
    }

    public function testNullValuesAreDumpedAsNull(): void
    {
        $this->pdo->exec('INSERT INTO users VALUES (3, NULL)');

        $output = $this->dumpUsersTable();

        $this->assertStringContainsString('(3,NULL)', $output);
    }

    public function testTableAndColumnNamesWithBackticksAreEscaped(): void
    {
        // SQLite supports MySQL-style backtick quoting including doubling
        $this->pdo->exec('CREATE TABLE `weird``name` (id INTEGER, `col``umn` TEXT)');
        $this->pdo->exec("INSERT INTO `weird``name` VALUES (1, 'x')");

        $dumpSettings = new DumpSettings(['complete-insert' => true]);
        $writer = new DumpWriter($dumpSettings);
        $writer->initialize($this->outputFile);

        $columnTypes = [
            'id' => ['is_numeric' => true, 'is_blob' => false, 'type' => 'int', 'type_sql' => 'int', 'is_virtual' => false],
            'col`umn' => ['is_numeric' => false, 'is_blob' => false, 'type' => 'text', 'type_sql' => 'text', 'is_virtual' => false],
        ];

        $dumper = new TableDataDumper(
            conn: $this->pdo,
            settings: $dumpSettings,
            db: new FakeTypeAdapter($this->pdo, $dumpSettings),
            writer: $writer,
            getColumnTypes: fn(string $table): array => $columnTypes,
            getTableWhere: fn(string $table): false => false,
            getTableLimit: fn(string $table): false => false,
        );

        $dumper->dump('weird`name');
        $writer->close();

        $output = file_get_contents($this->outputFile);

        $this->assertStringContainsString(
            "INSERT INTO `weird``name` (`id`, `col``umn`) VALUES (1,'x');",
            $output
        );
        // Comment headers quote identifiers too, like native mysqldump
        $this->assertStringContainsString('-- Dumping data for table `weird``name`', $output);
        $this->assertStringContainsString('-- Dumped table `weird``name` with 1 row(s)', $output);
    }

    public function testInfoHookReportsRowCount(): void
    {
        $payloads = [];
        $this->dumpUsersTable(overrides: [
            'info' => function (string $object, array $payload) use (&$payloads): void {
                $payloads[] = [$object, $payload];
            },
        ]);

        $this->assertSame(['table', ['name' => 'users', 'completed' => false, 'rowCount' => 0]], $payloads[0]);
        $this->assertSame(['table', ['name' => 'users', 'completed' => true, 'rowCount' => 2]], end($payloads));
    }
}
