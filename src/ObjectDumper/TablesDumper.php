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
    /**
     * @param Closure $iterateTables yields table names
     * @param Closure $matches function(string $name, array $excluded): bool
     * @param Closure $getTableStructure function(string $table): void
     * @param Closure $listValues function(string $table): void
     * @param Closure $getExcludedTables function(): array returns excluded tables
     * @param Closure $getNoData function(): array|bool returns no-data setting
     */
    public function __construct(
        private readonly Closure $iterateTables,
        private readonly Closure $matches,
        private readonly Closure $getTableStructure,
        private readonly Closure $listValues,
        private readonly Closure $getExcludedTables,
        private readonly Closure $getNoData
    ) {
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
