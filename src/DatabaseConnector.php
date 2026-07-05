<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump;

use Druidfi\Mysqldump\Exception\ConnectionException;
use PDO;
use PDOException;
use SensitiveParameter;

/**
 * Class DatabaseConnector
 *
 * Handles database connection logic for mysqldump-php.
 */
class DatabaseConnector
{
    private string $host;

    private string $dbName;

    private ?PDO $conn = null;

    /**
     * Constructor of DatabaseConnector.
     *
     * @param string $dsn PDO DSN connection string
     * @param string|null $user SQL account username
     * @param string|null $pass SQL account password
     * @param array<int, mixed> $pdoOptions PDO configured attributes
     * @throws ConnectionException
     */
    public function __construct(
        private readonly string $dsn = '',
        private readonly ?string $user = null,
        #[SensitiveParameter]
        private readonly ?string $pass = null,
        private readonly array $pdoOptions = []
    ) {
        $this->parseDsn($dsn);
    }

    /**
     * Parse DSN string and extract dbname value
     * Several examples of a DSN string
     *   mysql:host=localhost;dbname=testdb
     *   mysql:host=localhost;port=3307;dbname=testdb
     *   mysql:unix_socket=/tmp/mysql.sock;dbname=testdb
     *
     * @param string $dsn dsn string to parse
     * @throws ConnectionException
     */
    private function parseDsn(string $dsn): void
    {
        if (empty($dsn) || !($pos = strpos($dsn, ':'))) {
            throw new ConnectionException('Empty DSN string');
        }

        $dbType = strtolower(substr($dsn, 0, $pos));

        if (empty($dbType)) {
            throw new ConnectionException('Missing database type from DSN string');
        }

        $data = [];

        foreach (explode(';', substr($dsn, $pos + 1)) as $kvp) {
            if (str_contains($kvp, '=')) {
                [$param, $value] = explode('=', $kvp);
                $data[trim(strtolower($param))] = $value;
            }
        }

        if (empty($data['host']) && empty($data['unix_socket'])) {
            throw new ConnectionException('Missing host from DSN string');
        }

        if (empty($data['dbname'])) {
            throw new ConnectionException('Missing database name from DSN string');
        }

        $this->host = (!empty($data['host'])) ? $data['host'] : $data['unix_socket'];
        $this->dbName = $data['dbname'];
    }

    /**
     * Connect to the database with PDO.
     *
     * @return PDO The PDO connection
     * @throws ConnectionException
     */
    public function connect(): PDO
    {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            // Build default PDO options with compatibility for PHP 8.5 deprecations.
            // Persistent connections are intentionally not enabled by default: a dump
            // is a one-shot operation and a recycled handle may carry over session
            // state; opt in via the pdoOptions constructor argument if needed.
            $defaultOptions = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                // Don't convert empty strings to SQL NULL values on data fetches.
                PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
            ];

            // Handle deprecated PDO::MYSQL_ATTR_USE_BUFFERED_QUERY in PHP 8.5.
            // Prefer Pdo\Mysql::ATTR_USE_BUFFERED_QUERY when available; fall back otherwise.
            $mysqlBufferedQueryAttr = null;
            if (class_exists(\Pdo\Mysql::class) && defined('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY')) {
                $mysqlBufferedQueryAttr = constant('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY');
            } elseif (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
                $mysqlBufferedQueryAttr = constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY');
            }
            if ($mysqlBufferedQueryAttr !== null) {
                $defaultOptions[$mysqlBufferedQueryAttr] = false;
            }

            $options = array_replace_recursive($defaultOptions, $this->pdoOptions);

            $this->conn = new PDO($this->dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            $message = sprintf("Connection to %s failed with message: %s", $this->host, $e->getMessage());
            throw new ConnectionException($message);
        }

        return $this->conn;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getDbName(): string
    {
        return $this->dbName;
    }
}