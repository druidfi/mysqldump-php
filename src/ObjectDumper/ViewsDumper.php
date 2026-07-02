<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump\ObjectDumper;

use Closure;

class ViewsDumper implements DumperInterface
{
    public function __construct(
        private readonly Closure $iterateViews,
        private readonly Closure $matches,
        private readonly Closure $getViewStructureTable,
        private readonly Closure $getViewStructureView
    ) {
    }

    public function dump(): void
    {
        $iterate = $this->iterateViews;
        $matches = $this->matches;
        $structTable = $this->getViewStructureTable;
        $structView = $this->getViewStructureView;

        // First pass: stand-in tables
        foreach ($iterate() as $view) {
            $structTable($view);
        }
        // Second pass: actual views
        foreach ($iterate() as $view) {
            $structView($view);
        }
    }
}
