<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump;

use Druidfi\Mysqldump\Exception\ConnectionException;
use PDO;

/**
 * Provides the PDO connection and connection metadata used during a dump.
 *
 * The default implementation is DatabaseConnector, which builds a PDO
 * connection from a DSN string. Implement this interface to supply an
 * existing PDO instance or a custom connection strategy, and inject it
 * with Mysqldump::setConnector() before calling start().
 */
interface ConnectionInterface
{
    /**
     * Return the PDO connection, establishing it if necessary.
     * Called once per dump; implementations should return the same
     * connection on repeated calls.
     *
     * @throws ConnectionException when the connection cannot be established
     */
    public function connect(): PDO;

    /**
     * Host name (or socket path) written to the dump file header.
     */
    public function getHost(): string;

    /**
     * Name of the database being dumped; used in SHOW statements and comments.
     */
    public function getDbName(): string;
}
