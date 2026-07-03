<?php

declare(strict_types=1);

namespace Druidfi\Mysqldump\Exception;

/**
 * Thrown when the database connection cannot be established:
 * malformed DSN strings or PDO connection failures.
 */
class ConnectionException extends MysqldumpException
{
}
