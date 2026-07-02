<?php

declare(strict_types=1);

namespace Druidfi\Mysqldump;

/**
 * SQL statement type used for dumping table data.
 * The values match native mysqldump output byte for byte,
 * including the double space in INSERT  IGNORE.
 */
enum InsertType: string
{
    case Insert = 'INSERT';
    case InsertIgnore = 'INSERT  IGNORE';
    case Replace = 'REPLACE';
}
