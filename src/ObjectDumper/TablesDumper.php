<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump\ObjectDumper;

use Closure;

/**
 * TablesDumper coordinates dumping of table structures and data rows.
 * It relies on callbacks provided by Mysqldump to avoid duplicating logic.
 */
class TablesDumper implements DumperInterface
{
    private Closure $iterateTables;
    private Closure $matches;
    private Closure $getTableStructure;
    private Closure $listValues;
    private Closure $getExcludedTables;
    private Closure $getNoData;

    /**
     * @param Closure $iterateTables yields table names
     * @param Closure $matches function(string $name, array $excluded): bool
     * @param Closure $getTableStructure function(string $table): void
     * @param Closure $listValues function(string $table): void
     * @param Closure $getExcludedTables function(): array returns excluded tables
     * @param Closure $getNoData function(): array|bool returns no-data setting
     */
    public function __construct(
        Closure $iterateTables,
        Closure $matches,
        Closure $getTableStructure,
        Closure $listValues,
        Closure $getExcludedTables,
        Closure $getNoData
    ) {
        $this->iterateTables = $iterateTables;
        $this->matches = $matches;
        $this->getTableStructure = $getTableStructure;
        $this->listValues = $listValues;
        $this->getExcludedTables = $getExcludedTables;
        $this->getNoData = $getNoData;
    }

    public function dump(): void
    {
        $iterate = $this->iterateTables;
        $matches = $this->matches;
        $struct = $this->getTableStructure;
        $list = $this->listValues;
        $noDataGetter = $this->getNoData;
        $getExcluded = $this->getExcludedTables;

        foreach ($iterate() as $table) {
            // Skip excluded tables
            $excluded = $getExcluded();
            if (!empty($excluded) && $matches($table, $excluded)) {
                continue;
            }

            // Structure
            $struct($table);

            // Rows (respecting no-data settings via caller-provided logic inside listValues/struct)
            $list($table);
        }
    }
}
