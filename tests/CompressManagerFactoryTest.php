<?php

namespace Druidfi\Mysqldump\Tests;

use Druidfi\Mysqldump\Compress\CompressBzip2;
use Druidfi\Mysqldump\Compress\CompressGzip;
use Druidfi\Mysqldump\Compress\CompressGzipstream;
use Druidfi\Mysqldump\Compress\CompressManagerFactory;
use Druidfi\Mysqldump\Compress\CompressNone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Druidfi\Mysqldump\Exception\ConfigurationException;

#[CoversClass(CompressManagerFactory::class)]
class CompressManagerFactoryTest extends TestCase
{
    public function testCreateCommonMethods(): void
    {
        // Test only methods that don't require uncommon extensions
        $common = [
            CompressManagerFactory::NONE => CompressNone::class,
            CompressManagerFactory::GZIP => CompressGzip::class,
            CompressManagerFactory::BZIP2 => CompressBzip2::class,
            CompressManagerFactory::GZIPSTREAM => CompressGzipstream::class,
        ];

        foreach ($common as $method => $expectedClass) {
            try {
                $instance = CompressManagerFactory::create($method, 3);
                $this->assertInstanceOf($expectedClass, $instance, "Factory did not return $expectedClass for $method");
            } catch (ConfigurationException $e) {
                // If environment lacks required functions, skip gracefully
                $this->markTestSkipped("Skipping $method due to missing extension: {$e->getMessage()}");
            }
        }
    }

    public function testCreateInvalidMethodThrows(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Compression method (Invalid) is not defined yet');
        CompressManagerFactory::create('Invalid');
    }
}
