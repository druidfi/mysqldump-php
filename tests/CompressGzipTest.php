<?php

declare(strict_types=1);

namespace Druidfi\Mysqldump\Tests;

use Druidfi\Mysqldump\Compress\CompressGzip;
use Druidfi\Mysqldump\Compress\CompressManagerFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Druidfi\Mysqldump\Compress\CompressGzip
 */
class CompressGzipTest extends TestCase
{
    public function testDefaultLevelIsZero(): void
    {
        $gzip = new CompressGzip();
        $this->assertSame(0, $this->getLevel($gzip));
    }

    /**
     * @dataProvider validLevelProvider
     */
    public function testValidLevelIsStored(int $level): void
    {
        $gzip = new CompressGzip($level);
        $this->assertSame($level, $this->getLevel($gzip));
    }

    public static function validLevelProvider(): array
    {
        return [[1], [5], [9]];
    }

    /**
     * @dataProvider outOfRangeLevelProvider
     */
    public function testOutOfRangeLevelFallsBackToDefault(int $level): void
    {
        $gzip = new CompressGzip($level);
        $this->assertSame(0, $this->getLevel($gzip));
    }

    public static function outOfRangeLevelProvider(): array
    {
        return [[0], [10], [100]];
    }

    public function testFactoryPassesLevelToGzip(): void
    {
        $gzip = CompressManagerFactory::create(CompressManagerFactory::GZIP, 7);
        $this->assertSame(7, $this->getLevel($gzip));
    }

    public function testFactoryWithDefaultLevelCreatesGzipWithLevelZero(): void
    {
        $gzip = CompressManagerFactory::create(CompressManagerFactory::GZIP);
        $this->assertSame(0, $this->getLevel($gzip));
    }

    public function testWriteAndReadWithLevel(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'mysqldump_test_') . '.gz';

        try {
            $gzip = new CompressGzip(1);
            $gzip->open($tmpFile);
            $gzip->write('test data');
            $gzip->close();

            $this->assertFileExists($tmpFile);
            $this->assertGreaterThan(0, filesize($tmpFile));

            // Verify the file is valid gzip by reading it back
            $handle = gzopen($tmpFile, 'rb');
            $this->assertNotFalse($handle);
            $content = gzread($handle, 1024);
            gzclose($handle);
            $this->assertSame('test data', $content);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    private function getLevel(CompressGzip $gzip): int
    {
        $ref = new \ReflectionProperty(CompressGzip::class, 'level');
        $ref->setAccessible(true);
        return $ref->getValue($gzip);
    }
}
