<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump\ObjectDumper;

interface DumperInterface
{
    public function dump(): void;
}
