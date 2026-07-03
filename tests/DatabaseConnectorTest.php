<?php

namespace Druidfi\Mysqldump\Tests;

use Druidfi\Mysqldump\DatabaseConnector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Exception;

#[CoversClass(DatabaseConnector::class)]
class DatabaseConnectorTest extends TestCase
{
    public function testParseDsnValidHostAndDbname(): void
    {
        $dsn = 'mysql:host=localhost;dbname=testdb';
        $connector = new DatabaseConnector($dsn, 'user', 'pass');
        $this->assertEquals('localhost', $this->getPrivate($connector, 'host'));
        $this->assertEquals('testdb', $this->getPrivate($connector, 'dbName'));
    }

    public function testParseDsnUnixSocket(): void
    {
        $dsn = 'mysql:unix_socket=/tmp/mysql.sock;dbname=testdb';
        $connector = new DatabaseConnector($dsn, 'user', 'pass');
        $this->assertEquals('/tmp/mysql.sock', $this->getPrivate($connector, 'host'));
        $this->assertEquals('testdb', $this->getPrivate($connector, 'dbName'));
    }

    public function testEmptyDsnThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Empty DSN string');
        new DatabaseConnector('', 'user', 'pass');
    }

    public function testMissingDbTypeThrows(): void
    {
        $this->expectException(Exception::class);
        // Due to current implementation treating colon at position 0 as empty DSN
        $this->expectExceptionMessage('Empty DSN string');
        new DatabaseConnector(':host=localhost;dbname=test', 'user', 'pass');
    }

    public function testMissingHostThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing host from DSN string');
        new DatabaseConnector('mysql:dbname=test', 'user', 'pass');
    }

    public function testMissingDbnameThrows(): void    
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing database name from DSN string');
        new DatabaseConnector('mysql:host=localhost', 'user', 'pass');
    }

    private function getPrivate(object $object, string $var): mixed
    {
        $refl = new \ReflectionProperty($object::class, $var);
        return $refl->getValue($object);
    }
}
