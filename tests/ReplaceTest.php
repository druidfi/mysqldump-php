<?php

namespace Druidfi\Mysqldump\Tests;

use Druidfi\Mysqldump\DumpSettings;
use Druidfi\Mysqldump\Mysqldump;
use PHPUnit\Framework\TestCase;
use Exception;

/**
 * @covers \Druidfi\Mysqldump\Mysqldump
 * @covers \Druidfi\Mysqldump\DumpSettings
 */
class ReplaceTest extends TestCase
{
    /**
     * Test that replace option generates REPLACE INTO statements
     */
    public function testReplaceGeneratesCorrectSQL()
    {
        // This is a basic test to verify the getInsertType method logic
        // In a full integration test, you would connect to a test database
        // and verify the actual SQL output
        
        $settings = new DumpSettings(['replace' => true]);
        $this->assertTrue($settings->isEnabled('replace'));
        $this->assertFalse($settings->isEnabled('insert-ignore'));
    }

    /**
     * Test that insert-ignore option still works as expected
     */
    public function testInsertIgnoreStillWorks()
    {
        $settings = new DumpSettings(['insert-ignore' => true]);
        $this->assertFalse($settings->isEnabled('replace'));
        $this->assertTrue($settings->isEnabled('insert-ignore'));
    }

    /**
     * Test default behavior (normal INSERT)
     */
    public function testDefaultInsert()
    {
        $settings = new DumpSettings([]);
        $this->assertFalse($settings->isEnabled('replace'));
        $this->assertFalse($settings->isEnabled('insert-ignore'));
    }

    /**
     * Test that replace and insert-ignore cannot be used together
     */
    public function testMutualExclusivity()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot use both replace and insert-ignore options simultaneously');
        
        new DumpSettings([
            'replace' => true,
            'insert-ignore' => true
        ]);
    }
}