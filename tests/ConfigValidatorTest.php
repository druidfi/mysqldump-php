<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump\Tests;

use Druidfi\Mysqldump\ConfigValidator;
use Exception;
use PHPUnit\Framework\TestCase;

/**
 * Tests that ConfigValidator properly reads and uses PHP Attributes
 * from ConfigOption class for validation and deprecation checking.
 */
class ConfigValidatorTest extends TestCase
{
    public function testGetDefaultsReturnsAttributeValues(): void
    {
        $defaults = ConfigValidator::getDefaults();

        // Check that defaults are loaded from DefaultValue attributes
        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('compress', $defaults);
        $this->assertEquals('None', $defaults['compress']);
        $this->assertArrayHasKey('compress-level', $defaults);
        $this->assertEquals(0, $defaults['compress-level']);
        $this->assertArrayHasKey('include-tables', $defaults);
        $this->assertEquals([], $defaults['include-tables']);
    }

    public function testValidateAcceptsValidCompressValue(): void
    {
        // Should not throw - 'Gzip' is in allowed values
        $this->expectNotToPerformAssertions();
        ConfigValidator::validate('compress', 'Gzip');
    }

    public function testValidateRejectsInvalidCompressValue(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Must be a valid compression method');
        
        ConfigValidator::validate('compress', 'InvalidCompression');
    }

    public function testValidateAcceptsValidCompressionLevel(): void
    {
        // Should not throw - compression level 5 is within 0-9 range
        $this->expectNotToPerformAssertions();
        ConfigValidator::validate('compress-level', 5);
    }

    public function testValidateRejectsCompressionLevelTooLow(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Compression level must be between 0 and 9');
        
        ConfigValidator::validate('compress-level', -1);
    }

    public function testValidateRejectsCompressionLevelTooHigh(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Compression level must be between 0 and 9');
        
        ConfigValidator::validate('compress-level', 10);
    }

    public function testValidateAcceptsValidCharacterSet(): void
    {
        // Should not throw - 'utf8' is in allowed values
        $this->expectNotToPerformAssertions();
        ConfigValidator::validate('default-character-set', 'utf8');
    }

    public function testValidateRejectsInvalidCharacterSet(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Must be utf8 or utf8mb4');
        
        ConfigValidator::validate('default-character-set', 'latin1');
    }

    public function testCheckDeprecatedReturnsNullForNonDeprecatedOption(): void
    {
        $result = ConfigValidator::checkDeprecated('compress');
        
        $this->assertNull($result);
    }

    public function testCheckDeprecatedReturnsInfoForDeprecatedOption(): void
    {
        $result = ConfigValidator::checkDeprecated('disable-foreign-keys-check');
        
        $this->assertIsArray($result);
        $this->assertTrue($result['deprecated']);
        $this->assertStringContainsString('deprecated', $result['reason']);
        $this->assertStringContainsString('init_commands', $result['alternative']);
        $this->assertEquals('2.0', $result['since']);
    }

    public function testValidateAllAcceptsValidSettings(): void
    {
        $settings = [
            'compress' => 'Gzip',
            'compress-level' => 6,
            'default-character-set' => 'utf8mb4',
            'add-locks' => true,
        ];

        // Should not throw
        $this->expectNotToPerformAssertions();
        ConfigValidator::validateAll($settings);
    }

    public function testValidateAllRejectsInvalidSettings(): void
    {
        $settings = [
            'compress' => 'InvalidMethod',
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Must be a valid compression method');
        
        ConfigValidator::validateAll($settings);
    }

    public function testValidateSkipsUnknownOptions(): void
    {
        // Unknown options should not cause validation errors
        // (they might be handled elsewhere or be custom extensions)
        $this->expectNotToPerformAssertions();
        ConfigValidator::validate('unknown-option', 'some-value');
    }

    public function testValidateNetBufferLengthMinConstraint(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Net buffer length must be at least 1024');
        
        ConfigValidator::validate('net_buffer_length', 512);
    }
}
