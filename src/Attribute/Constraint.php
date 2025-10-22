<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump\Attribute;

use Attribute;

/**
 * Marks a configuration setting or parameter with validation constraints.
 * Can specify allowed values, ranges, or custom validation rules.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::TARGET_CLASS_CONSTANT)]
class Constraint
{
    public function __construct(
        public readonly ?array $allowedValues = null,
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?string $pattern = null,
        public readonly ?string $message = null
    ) {
    }
}
