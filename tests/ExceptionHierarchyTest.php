<?php

declare(strict_types=1);

namespace Druidfi\Mysqldump\Tests;

use Druidfi\Mysqldump\Exception\ConfigurationException;
use Druidfi\Mysqldump\Exception\ConnectionException;
use Druidfi\Mysqldump\Exception\DumpException;
use Druidfi\Mysqldump\Exception\MysqldumpException;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the exception hierarchy: every library exception must extend
 * MysqldumpException, which itself extends the native Exception so
 * pre-3.x catch (Exception) blocks keep working.
 */
#[CoversClass(MysqldumpException::class)]
#[CoversClass(ConnectionException::class)]
#[CoversClass(ConfigurationException::class)]
#[CoversClass(DumpException::class)]
class ExceptionHierarchyTest extends TestCase
{
    /**
     * @return array<string, array{class-string}>
     */
    public static function exceptionClassProvider(): array
    {
        return [
            'connection' => [ConnectionException::class],
            'configuration' => [ConfigurationException::class],
            'dump' => [DumpException::class],
        ];
    }

    #[DataProvider('exceptionClassProvider')]
    public function testExtendsMysqldumpException(string $class): void
    {
        $exception = new $class('test');

        $this->assertInstanceOf(MysqldumpException::class, $exception);
    }

    public function testBaseExceptionKeepsNativeCatchBlocksWorking(): void
    {
        $this->assertInstanceOf(Exception::class, new MysqldumpException('test'));
    }
}
