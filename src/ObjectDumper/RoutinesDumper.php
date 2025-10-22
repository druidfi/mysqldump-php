<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump\ObjectDumper;

use Closure;

/**
 * Dumps procedures and functions.
 */
class RoutinesDumper implements DumperInterface
{
    private Closure $iterateProcedures;
    private Closure $iterateFunctions;
    private Closure $getProcedureStructure;
    private Closure $getFunctionStructure;

    public function __construct(
        Closure $iterateProcedures,
        Closure $iterateFunctions,
        Closure $getProcedureStructure,
        Closure $getFunctionStructure
    ) {
        $this->iterateProcedures = $iterateProcedures;
        $this->iterateFunctions = $iterateFunctions;
        $this->getProcedureStructure = $getProcedureStructure;
        $this->getFunctionStructure = $getFunctionStructure;
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
