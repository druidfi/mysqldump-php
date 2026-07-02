<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump\ObjectDumper;

use Closure;

class EventsDumper implements DumperInterface
{
    public function __construct(
        private readonly Closure $iterateEvents,
        private readonly Closure $getEventStructure
    ) {
    }

    public function dump(): void
    {
        $iterate = $this->iterateEvents;
        $struct = $this->getEventStructure;

        foreach ($iterate() as $name) {
            $struct($name);
        }
    }
}
