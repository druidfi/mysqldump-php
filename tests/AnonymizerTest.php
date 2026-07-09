<?php

namespace Druidfi\Mysqldump\Tests;

use Druidfi\Mysqldump\Anonymizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Anonymizer::class)]
class AnonymizerTest extends TestCase
{
    public function testColumnMapTransformsMappedColumns(): void
    {
        $hook = Anonymizer::columnMap([
            'customers' => [
                'email' => Anonymizer::email(),
                'ssn' => Anonymizer::fixed('REDACTED'),
            ],
        ]);

        $row = $hook('customers', ['id' => 1, 'email' => 'john@real.example', 'ssn' => '010101-123X']);

        $this->assertSame(1, $row['id']);
        $this->assertMatchesRegularExpression('/^user-[0-9a-f]{12}@example\.com$/', $row['email']);
        $this->assertSame('REDACTED', $row['ssn']);
    }

    public function testColumnMapLeavesUnmappedTablesUntouched(): void
    {
        $hook = Anonymizer::columnMap(['customers' => ['email' => Anonymizer::fixed('x')]]);

        $row = ['id' => 1, 'email' => 'john@real.example'];

        $this->assertSame($row, $hook('orders', $row));
    }

    public function testColumnMapIgnoresMappedColumnsMissingFromRow(): void
    {
        $hook = Anonymizer::columnMap(['customers' => ['missing' => Anonymizer::fixed('x')]]);

        $row = ['id' => 1];

        $this->assertSame($row, $hook('customers', $row));
    }

    public function testColumnMapPassesValueAndRowToTransformer(): void
    {
        $hook = Anonymizer::columnMap([
            'users' => [
                'display_name' => fn (mixed $value, array $row): string => 'user-' . $row['id'],
            ],
        ]);

        $row = $hook('users', ['id' => 7, 'display_name' => 'John Doe']);

        $this->assertSame('user-7', $row['display_name']);
    }

    public function testFixedPreservesNull(): void
    {
        $transform = Anonymizer::fixed('x');

        $this->assertSame('x', $transform('secret'));
        $this->assertNull($transform(null));
    }

    public function testMaskKeepsLengthAndPrefix(): void
    {
        $transform = Anonymizer::mask(2);

        $this->assertSame('04********', $transform('0401234567'));
        $this->assertSame('ab', $transform('ab'));
        $this->assertNull($transform(null));
    }

    public function testMaskWithoutPrefixMasksEverything(): void
    {
        $transform = Anonymizer::mask();

        $this->assertSame('******', $transform('secret'));
    }

    public function testMaskCountsMultibyteCharactersNotBytes(): void
    {
        $transform = Anonymizer::mask(1);

        $this->assertSame('Ä***', $transform('Äiti'));
    }

    public function testHashIsDeterministicAndSalted(): void
    {
        $plain = Anonymizer::hash();
        $salted = Anonymizer::hash('pepper');

        $this->assertSame($plain('john'), $plain('john'));
        $this->assertSame(hash('sha256', 'john'), $plain('john'));
        $this->assertNotSame($plain('john'), $salted('john'));
        $this->assertNull($plain(null));
    }

    public function testEmailIsDeterministicValidAndUnique(): void
    {
        $transform = Anonymizer::email();

        $this->assertSame($transform('john@real.example'), $transform('john@real.example'));
        $this->assertNotSame($transform('john@real.example'), $transform('jane@real.example'));
        $this->assertMatchesRegularExpression('/^user-[0-9a-f]{12}@example\.com$/', $transform('john@real.example'));
        $this->assertNull($transform(null));
    }

    public function testEmailUsesCustomDomainAndSalt(): void
    {
        $plain = Anonymizer::email();
        $custom = Anonymizer::email('anon.invalid', 'pepper');

        $this->assertStringEndsWith('@anon.invalid', $custom('john@real.example'));
        $this->assertNotSame($plain('john@real.example'), $custom('john@real.example'));
    }
}
