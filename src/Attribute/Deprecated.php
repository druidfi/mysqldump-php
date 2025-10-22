<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump\Attribute;

use Attribute;

/**
 * Marks a class, method, property, or configuration setting as deprecated.
 * Provides information about the deprecation and suggested alternatives.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS_CONSTANT)]
class Deprecated
{
    public function __construct(
        public readonly string $reason,
        public readonly ?string $alternative = null,
        public readonly ?string $since = null,
        public readonly ?string $removeIn = null
    ) {
    }
}
