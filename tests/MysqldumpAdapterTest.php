<?php

namespace Druidfi\Mysqldump\Tests;

use Druidfi\Mysqldump\Mysqldump;
use Druidfi\Mysqldump\Tests\Doubles\FakeTypeAdapter;
use PHPUnit\Framework\TestCase;
use Exception;
use PDO;

/**
 * @covers \Druidfi\Mysqldump
 */
class MysqldumpAdapterTest extends TestCase
{
    public function testAddTypeAdapterRejectsInvalidClass()
    {
        $dump = new Mysqldump('mysql:host=localhost;dbname=test', 'user', 'pass');
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Adapter .* is not instance of/');
        $dump->addTypeAdapter(\stdClass::class);
    }

    public function testGetAdapterUsesConfiguredAdapter()
    {
        $dump = new Mysqldump('mysql:host=localhost;dbname=test', 'user', 'pass');
        $dump->addTypeAdapter(FakeTypeAdapter::class);
        $pdo = new PDO('sqlite::memory:');
        $adapter = $dump->getAdapter($pdo);
        $this->assertInstanceOf(FakeTypeAdapter::class, $adapter);
    }

    public function testGetTableWhereReturnsFalseWhenUnset()
    {
        $dump = new Mysqldump('mysql:host=localhost;dbname=test', 'user', 'pass');
        $this->assertFalse($dump->getTableWhere('unknown_table'));
    }
}
