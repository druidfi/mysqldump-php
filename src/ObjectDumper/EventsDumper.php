<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump\ObjectDumper;

use Closure;

class EventsDumper implements DumperInterface
{
    private Closure $iterateEvents;
    private Closure $getEventStructure;

    public function __construct(
        Closure $iterateEvents,
        Closure $getEventStructure
    ) {
        $this->iterateEvents = $iterateEvents;
        $this->getEventStructure = $getEventStructure;
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
