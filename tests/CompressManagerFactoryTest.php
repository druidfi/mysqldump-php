<?php

namespace Druidfi\Mysqldump\Tests;

use Druidfi\Mysqldump\Compress\CompressManagerFactory;
use PHPUnit\Framework\TestCase;
use Exception;

/**
 * @covers \Druidfi\Mysqldump\Compress\CompressManagerFactory
 */
class CompressManagerFactoryTest extends TestCase
{
    public function testCreateCommonMethods()
    {
        // Test only methods that don't require uncommon extensions
        $common = [
            CompressManagerFactory::NONE,
            CompressManagerFactory::GZIP,
            CompressManagerFactory::BZIP2,
            CompressManagerFactory::GZIPSTREAM,
        ];

        foreach ($common as $method) {
            try {
                $instance = CompressManagerFactory::create($method, 3);
                $this->assertIsObject($instance, "Factory did not return object for $method");
            } catch (Exception $e) {
                // If environment lacks required functions, skip gracefully
                $this->markTestSkipped("Skipping $method due to missing extension: {$e->getMessage()}");
            }
        }
    }

    public function testCreateInvalidMethodThrows()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Compression method (Invalid) is not defined yet');
        CompressManagerFactory::create('Invalid');
    }
}
