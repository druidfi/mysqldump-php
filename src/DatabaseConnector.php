<?php

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
    /**
     * @var string DSN connection string
     */
    private string $dsn;

    /**
     * @var string|null Username for database connection
     */
    private ?string $user;

    /**
     * @var string|null Password for database connection
     */
    private ?string $pass;

    /**
     * @var array PDO options
     */
    private array $pdoOptions;

    /**
     * @var string Database host
     */
    private string $host;

    /**
     * @var string Database name
     */
    private string $dbName;

    /**
     * @var PDO|null PDO connection
     */
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
            $options = array_replace_recursive([
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                // Don't convert empty strings to SQL NULL values on data fetches.
                PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
            ], $this->pdoOptions);

            $this->conn = new PDO($this->dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            $message = sprintf("Connection to %s failed with message: %s", $this->host, $e->getMessage());
            throw new Exception($message);
        }

        return $this->conn;
    }

    /**
     * Get the database host.
     *
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Get the database name.
     *
     * @return string
     */
    public function getDbName(): string
    {
        return $this->dbName;
    }
}