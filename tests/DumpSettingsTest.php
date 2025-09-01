<?php

namespace Druidfi\Mysqldump\Tests;

use Druidfi\Mysqldump\DumpSettings;
use PHPUnit\Framework\TestCase;
use Exception;

/**
 * @covers \Druidfi\Mysqldump\DumpSettings
 */
class DumpSettingsTest extends TestCase
{
    /**
     * Test that default settings are properly initialized
     */
    public function testDefaultSettings()
    {
        $settings = new DumpSettings([]);

        // Test a few key default settings
        $this->assertEquals('None', $settings->getCompressMethod());
        $this->assertEquals(0, $settings->getCompressLevel());
        $this->assertEquals('utf8', $settings->getDefaultCharacterSet());
        $this->assertEquals([], $settings->getIncludedTables());
        $this->assertEquals([], $settings->getExcludedTables());
        $this->assertEquals([], $settings->getNoData());
        $this->assertEquals(1000000, $settings->getNetBufferLength());
        
        // Test boolean settings
        $this->assertTrue($settings->isEnabled('add-locks'));
        $this->assertTrue($settings->isEnabled('disable-keys'));
        $this->assertFalse($settings->isEnabled('if-not-exists'));
        $this->assertFalse($settings->isEnabled('insert-ignore'));
        $this->assertFalse($settings->isEnabled('replace'));
    }
    
    /**
     * Test that custom settings override defaults
     */
    public function testCustomSettings()
    {
        $customSettings = [
            'compress' => 'Gzip',
            'compress-level' => 9,
            'default-character-set' => 'utf8mb4',
            'include-tables' => ['users', 'posts'],
            'exclude-tables' => ['logs'],
            'no-data' => ['cache'],
            'net_buffer_length' => 500000,
            'add-locks' => false,
            'disable-keys' => false,
            'if-not-exists' => true,
            'insert-ignore' => true,
        ];
        
        $settings = new DumpSettings($customSettings);
        
        // Test that custom settings are properly applied
        $this->assertEquals('Gzip', $settings->getCompressMethod());
        $this->assertEquals(9, $settings->getCompressLevel());
        $this->assertEquals('utf8mb4', $settings->getDefaultCharacterSet());
        $this->assertEquals(['users', 'posts'], $settings->getIncludedTables());
        $this->assertEquals(['logs'], $settings->getExcludedTables());
        $this->assertEquals(['cache'], $settings->getNoData());
        $this->assertEquals(500000, $settings->getNetBufferLength());
        
        // Test boolean settings
        $this->assertFalse($settings->isEnabled('add-locks'));
        $this->assertFalse($settings->isEnabled('disable-keys'));
        $this->assertTrue($settings->isEnabled('if-not-exists'));
        $this->assertTrue($settings->isEnabled('insert-ignore'));
    }
    
    /**
     * Test that invalid settings throw exceptions
     */
    public function testInvalidSettings()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unexpected value in dumpSettings');
        
        new DumpSettings(['invalid-setting' => true]);
    }
    
    /**
     * Test that invalid include/exclude tables throw exceptions
     */
    public function testInvalidTableSettings()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Include-tables and exclude-tables should be arrays');
        
        new DumpSettings(['include-tables' => 'users']);
    }
    
    /**
     * Test that include-views defaults to include-tables if not set
     */
    public function testIncludeViewsDefault()
    {
        $settings = new DumpSettings(['include-tables' => ['users', 'posts']]);
        
        $this->assertEquals(['users', 'posts'], $settings->getIncludedViews());
    }
    
    /**
     * Test that include-views can be set separately from include-tables
     */
    public function testIncludeViewsCustom()
    {
        $settings = new DumpSettings([
            'include-tables' => ['users', 'posts'],
            'include-views' => ['active_users']
        ]);
        
        $this->assertEquals(['active_users'], $settings->getIncludedViews());
    }
    
    /**
     * Test the setIncludedTables method
     */
    public function testSetIncludedTables()
    {
        $settings = new DumpSettings([]);
        $settings->setIncludedTables(['users', 'posts']);
        
        $this->assertEquals(['users', 'posts'], $settings->getIncludedTables());
    }
    
    /**
     * Test the setCompleteInsert method
     */
    public function testSetCompleteInsert()
    {
        $settings = new DumpSettings([]);
        $this->assertFalse($settings->isEnabled('complete-insert'));
        
        $settings->setCompleteInsert(true);
        $this->assertTrue($settings->isEnabled('complete-insert'));
        
        $settings->setCompleteInsert(false);
        $this->assertFalse($settings->isEnabled('complete-insert'));
    }
    
    /**
     * Test the skip* methods
     */
    public function testSkipMethods()
    {
        $settings = new DumpSettings([
            'skip-comments' => true,
            'skip-definer' => true,
            'skip-dump-date' => true,
            'skip-triggers' => true,
            'skip-tz-utc' => true
        ]);
        
        $this->assertTrue($settings->skipComments());
        $this->assertTrue($settings->skipDefiner());
        $this->assertTrue($settings->skipDumpDate());
        $this->assertTrue($settings->skipTriggers());
        $this->assertTrue($settings->skipTzUtc());
        
        $settings = new DumpSettings([]);
        
        $this->assertFalse($settings->skipComments());
        $this->assertFalse($settings->skipDefiner());
        $this->assertFalse($settings->skipDumpDate());
        $this->assertFalse($settings->skipTriggers());
        $this->assertFalse($settings->skipTzUtc());
    }
    
    /**
     * Test the get method
     */
    public function testGetMethod()
    {
        $settings = new DumpSettings(['net_buffer_length' => 500000]);
        
        $this->assertEquals('500000', $settings->get('net_buffer_length'));
    }
    
    /**
     * Test the getInitCommands method
     */
    public function testGetInitCommands()
    {
        // Default init commands
        $settings = new DumpSettings([]);
        $initCommands = $settings->getInitCommands();
        
        $this->assertCount(2, $initCommands);
        $this->assertEquals("SET NAMES utf8", $initCommands[0]);
        $this->assertEquals("SET TIME_ZONE='+00:00'", $initCommands[1]);
        
        // Custom character set
        $settings = new DumpSettings(['default-character-set' => 'utf8mb4']);
        $initCommands = $settings->getInitCommands();
        
        $this->assertCount(2, $initCommands);
        $this->assertEquals("SET NAMES utf8mb4", $initCommands[0]);
        $this->assertEquals("SET TIME_ZONE='+00:00'", $initCommands[1]);
        
        // Skip timezone
        $settings = new DumpSettings(['skip-tz-utc' => true]);
        $initCommands = $settings->getInitCommands();
        
        $this->assertCount(1, $initCommands);
        $this->assertEquals("SET NAMES utf8", $initCommands[0]);
    }

    /**
     * Test replace option defaults
     */
    public function testReplaceDefault()
    {
        $settings = new DumpSettings([]);
        $this->assertFalse($settings->isEnabled('replace'));
    }

    /**
     * Test replace option can be enabled
     */
    public function testReplaceEnabled()
    {
        $settings = new DumpSettings(['replace' => true]);
        $this->assertTrue($settings->isEnabled('replace'));
    }

    /**
     * Test that replace and insert-ignore cannot be used together
     */
    public function testReplaceAndInsertIgnoreMutualExclusion()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot use both replace and insert-ignore options simultaneously');
        
        new DumpSettings([
            'replace' => true,
            'insert-ignore' => true
        ]);
    }
}