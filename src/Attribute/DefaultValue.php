<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump\Attribute;

use Attribute;

/**
 * Marks a configuration setting with its default value.
 * Can be used on class constants or properties to document default configuration values.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT | Attribute::TARGET_PROPERTY)]
class DefaultValue
{
    public function __construct(
        public readonly mixed $value,
        public readonly ?string $description = null
    ) {
    }
}
