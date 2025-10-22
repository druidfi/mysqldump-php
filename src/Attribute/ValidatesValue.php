<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump\Attribute;

use Attribute;

/**
 * Marks a parameter or property that should be validated at runtime.
 * Can be used to trigger automatic validation of method parameters.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
class ValidatesValue
{
    public function __construct(
        public readonly ?string $type = null,
        public readonly bool $notNull = false,
        public readonly bool $notEmpty = false,
        public readonly ?array $allowedValues = null
    ) {
    }
}
