<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump\ObjectDumper;

use Closure;

class TriggersDumper implements DumperInterface
{
    private Closure $iterateTriggers;
    private Closure $getTriggerStructure;

    public function __construct(
        Closure $iterateTriggers,
        Closure $getTriggerStructure
    ) {
        $this->iterateTriggers = $iterateTriggers;
        $this->getTriggerStructure = $getTriggerStructure;
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
