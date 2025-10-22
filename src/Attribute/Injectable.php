<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump\Attribute;

use Attribute;

/**
 * Marks a class, property, or parameter as injectable for dependency injection.
 * Can specify the service identifier and whether it's required.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Injectable
{
    public function __construct(
        public readonly ?string $serviceId = null,
        public readonly bool $required = true,
        public readonly ?string $factory = null
    ) {
    }
}
