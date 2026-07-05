<?php

namespace Druidfi\Mysqldump\Tests;

use Druidfi\Mysqldump\Exception\ConnectionException;
use Druidfi\Mysqldump\Mysqldump;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Mysqldump::class)]
class MysqldumpTest extends TestCase
{
    public function testTableSpecificWhereConditionsWork(): void
    {
        $dump = new Mysqldump('mysql:host=localhost;dbname=test', 'testing', 'testing', [
            'where' => 'defaultWhere'
        ]);

        $dump->setTableWheres([
            'users' => 'date_registered > NOW() - INTERVAL 3 MONTH AND is_deleted=0',
            'logs' => 'date_registered > NOW() - INTERVAL 1 DAY',
            'posts' => 'active=1'
        ]);

        $this->assertEquals(
            'date_registered > NOW() - INTERVAL 3 MONTH AND is_deleted=0',
            $dump->getTableWhere('users')
        );

        $this->assertEquals(
            'defaultWhere',
            $dump->getTableWhere('non_overriden_table')
        );
    }

    public function testDSNWorks(): void
    {
        $user = 'user';
        $pass = 'password';
        $host = 'localhost';
        $dbName = 'test';
        $dsn = "mysql: host={$host}; dbname={$dbName};";
        $dump = new Mysqldump($dsn, $user, $pass);

        // Get the connector property
        $connector = $this->getPrivate($dump, 'connector');

        // Test the properties on the connector
        $this->assertEquals($dsn, $this->getPrivateFromObject($connector, 'dsn'), 'dsn is not set correctly');
        $this->assertEquals($user, $this->getPrivateFromObject($connector, 'user'), 'user is not set correctly');
        $this->assertEquals($pass, $this->getPrivateFromObject($connector, 'pass'), 'pass is not set correctly');
        $this->assertEquals($host, $this->getPrivateFromObject($connector, 'host'), 'host is not set correctly');
        $this->assertEquals($dbName, $this->getPrivateFromObject($connector, 'dbName'), 'dbName is not set correctly');
    }

    public function testDSNStringExits(): void
    {
        $this->expectException(ConnectionException::class);
        $dump = new Mysqldump('', 'testing', 'testing');
    }

    public function testHostExits(): void
    {
        $this->expectException(ConnectionException::class);
        $dump = new Mysqldump('mysql: dbname=test;', 'testing', 'testing');
    }

    public function testDBTypeExists(): void
    {
        $this->expectException(ConnectionException::class);
        $dump = new Mysqldump('host=localhost; dbname=test;', 'testing', 'testing');
    }

    public function testDBNameExists(): void
    {
        $this->expectException(ConnectionException::class);
        $dump = new Mysqldump('mysql: host=localhost', 'testing', 'testing');
    }

    public function testTableSpecificLimitsWork(): void
    {
        $dump = new Mysqldump('mysql: host=localhost; dbname=test;', 'testing', 'testing');

        $dump->setTableLimits([
            'users' => 200,
            'logs' => 500,
            'table_with_invalid_limit' => '41923, 42992',
            'table_with_range_limit' => [100, 300],
            'table_with_range_limit2' => [1, 1],
            'table_with_invalid_range_limit' => [100],
            'table_with_invalid_range_limit2' => [100, 300, 400],

        ]);

        $this->assertEquals(200, $dump->getTableLimit('users'));
        $this->assertEquals(500, $dump->getTableLimit('logs'));
        $this->assertFalse($dump->getTableLimit('table_with_invalid_limit'));
        $this->assertFalse($dump->getTableLimit('table_name_with_no_limit'));
        $this->assertEquals('100,300', $dump->getTableLimit('table_with_range_limit'));
        $this->assertFalse($dump->getTableLimit('table_with_invalid_range_limit'));
        $this->assertFalse($dump->getTableLimit('table_with_invalid_range_limit2'));
        $this->assertFalse($dump->getTableLimit('table_with_invalid_range_limit3'));
    }

    private function getPrivate(Mysqldump $dump, string $var): mixed
    {
        $reflectionProperty = new \ReflectionProperty(Mysqldump::class, $var);
        return $reflectionProperty->getValue($dump);
    }

    private function getPrivateFromObject(object $object, string $var): mixed
    {
        $reflectionProperty = new \ReflectionProperty($object::class, $var);
        return $reflectionProperty->getValue($object);
    }
}
