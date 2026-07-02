<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump\ObjectDumper;

use Closure;

class TriggersDumper implements DumperInterface
{
    public function __construct(
        private readonly Closure $iterateTriggers,
        private readonly Closure $getTriggerStructure
    ) {
    }

    public function dump(): void
    {
        $iterate = $this->iterateTriggers;
        $struct = $this->getTriggerStructure;

        foreach ($iterate() as $name) {
            $struct($name);
        }
    }
}
