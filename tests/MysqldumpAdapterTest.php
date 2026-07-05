<?php

namespace Druidfi\Mysqldump\Tests;

use Druidfi\Mysqldump\Mysqldump;
use Druidfi\Mysqldump\Tests\Doubles\FakeTypeAdapter;
use Druidfi\Mysqldump\TypeAdapter\TypeAdapterMysql;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Druidfi\Mysqldump\Exception\ConfigurationException;
use PDO;
use ReflectionProperty;

#[CoversClass(Mysqldump::class)]
class MysqldumpAdapterTest extends TestCase
{
    public function testAddTypeAdapterRejectsInvalidClass(): void
    {
        $dump = new Mysqldump('mysql:host=localhost;dbname=test', 'user', 'pass');
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/Adapter .* is not instance of/');
        $dump->addTypeAdapter(\stdClass::class);
    }

    public function testGetAdapterUsesConfiguredAdapter(): void
    {
        $dump = new Mysqldump('mysql:host=localhost;dbname=test', 'user', 'pass');
        $dump->addTypeAdapter(FakeTypeAdapter::class);
        $pdo = new PDO('sqlite::memory:');
        $adapter = $dump->getAdapter($pdo);
        $this->assertInstanceOf(FakeTypeAdapter::class, $adapter);
        $this->assertSame($pdo, $adapter->conn);
    }

    public function testAddTypeAdapterDoesNotLeakAcrossInstances(): void
    {
        $dump = new Mysqldump('mysql:host=localhost;dbname=test', 'user', 'pass');
        $dump->addTypeAdapter(FakeTypeAdapter::class);

        // A second instance must not inherit the adapter set on the first one.
        $other = new Mysqldump('mysql:host=localhost;dbname=test', 'user', 'pass');
        $refl = new ReflectionProperty(Mysqldump::class, 'adapterClass');
        $this->assertSame(FakeTypeAdapter::class, $refl->getValue($dump));
        $this->assertSame(TypeAdapterMysql::class, $refl->getValue($other));
    }

    public function testGetTableWhereReturnsFalseWhenUnset(): void
    {
        $dump = new Mysqldump('mysql:host=localhost;dbname=test', 'user', 'pass');
        $this->assertFalse($dump->getTableWhere('unknown_table'));
    }
}
