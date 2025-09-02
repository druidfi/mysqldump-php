<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump\ObjectDumper;

use Closure;

class ViewsDumper implements DumperInterface
{
    private Closure $iterateViews;
    private Closure $matches;
    private Closure $getViewStructureTable;
    private Closure $getViewStructureView;

    public function __construct(
        Closure $iterateViews,
        Closure $matches,
        Closure $getViewStructureTable,
        Closure $getViewStructureView
    ) {
        $this->iterateViews = $iterateViews;
        $this->matches = $matches;
        $this->getViewStructureTable = $getViewStructureTable;
        $this->getViewStructureView = $getViewStructureView;
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
