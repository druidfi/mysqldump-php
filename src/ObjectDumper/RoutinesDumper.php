<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump\ObjectDumper;

use Closure;

/**
 * Dumps procedures and functions.
 */
class RoutinesDumper implements DumperInterface
{
    public function __construct(
        private readonly Closure $iterateProcedures,
        private readonly Closure $iterateFunctions,
        private readonly Closure $getProcedureStructure,
        private readonly Closure $getFunctionStructure
    ) {
    }

    public function dump(): void
    {
        $itProc = $this->iterateProcedures;
        $itFunc = $this->iterateFunctions;
        $procStruct = $this->getProcedureStructure;
        $funcStruct = $this->getFunctionStructure;

        // Preserve original behavior: dump functions first, then procedures
        foreach ($itFunc() as $f) {
            $funcStruct($f);
        }
        foreach ($itProc() as $p) {
            $procStruct($p);
        }
    }
}
