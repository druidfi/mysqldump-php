<?php
declare(strict_types=1);

namespace Druidfi\Mysqldump;

use Exception;
use PDO;
use PDOException;

/**
 * Class DatabaseConnector
 * 
 * Handles database connection logic for mysqldump-php.
 */
class DatabaseConnector
{
    private string $dsn;

    private ?string $user;

    private ?string $pass;

    private array $pdoOptions;

    private string $host;

    private string $dbName;

    private ?PDO $conn = null;

    /**
     * Constructor of DatabaseConnector.
     *
     * @param string $dsn PDO DSN connection string
     * @param string|null $user SQL account username
     * @param string|null $pass SQL account password
     * @param array $pdoOptions PDO configured attributes
     * @throws Exception
     */
    public function __construct(
        string $dsn = '',
        ?string $user = null,
        ?string $pass = null,
        array $pdoOptions = []
    ) {
        $this->dsn = $this->parseDsn($dsn);
        $this->user = $user;
        $this->pass = $pass;
        $this->pdoOptions = $pdoOptions;
    }

    /**
     * Parse DSN string and extract dbname value
     * Several examples of a DSN string
     *   mysql:host=localhost;dbname=testdb
     *   mysql:host=localhost;port=3307;dbname=testdb
     *   mysql:unix_socket=/tmp/mysql.sock;dbname=testdb
     *
     * @param string $dsn dsn string to parse
     * @return string The parsed DSN
     * @throws Exception
     */
    private function parseDsn(string $dsn): string
    {
        if (empty($dsn) || !($pos = strpos($dsn, ':'))) {
            throw new Exception('Empty DSN string');
        }

        $dbType = strtolower(substr($dsn, 0, $pos));

        if (empty($dbType)) {
            throw new Exception('Missing database type from DSN string');
        }

        $data = [];

        foreach (explode(';', substr($dsn, $pos + 1)) as $kvp) {
            if (str_contains($kvp, '=')) {
                list($param, $value) = explode('=', $kvp);
                $data[trim(strtolower($param))] = $value;
            }
        }

        if (empty($data['host']) && empty($data['unix_socket'])) {
            throw new Exception('Missing host from DSN string');
        }

        if (empty($data['dbname'])) {
            throw new Exception('Missing database name from DSN string');
        }

        $this->host = (!empty($data['host'])) ? $data['host'] : $data['unix_socket'];
        $this->dbName = $data['dbname'];

        return $dsn;
    }

    /**
     * Connect to the database with PDO.
     *
     * @return PDO The PDO connection
     * @throws Exception
     */
    public function connect(): PDO
    {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            // Build default PDO options with compatibility for PHP 8.5 deprecations
            $defaultOptions = [
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                // Don't convert empty strings to SQL NULL values on data fetches.
                PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
            ];

            // Handle deprecated PDO::MYSQL_ATTR_USE_BUFFERED_QUERY in PHP 8.5.
            // Prefer Pdo\Mysql::ATTR_USE_BUFFERED_QUERY when available; fall back otherwise.
            $mysqlBufferedQueryAttr = null;
            if (class_exists('Pdo\\Mysql') && defined('Pdo\\Mysql::ATTR_USE_BUFFERED_QUERY')) {
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
            throw new Exception($message);
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