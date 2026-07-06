<?php

namespace Druidfi\Mysqldump\Tests\Doubles;

use Druidfi\Mysqldump\ConnectionInterface;
use PDO;

class FakeConnection implements ConnectionInterface
{
    public int $connectCalls = 0;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $host = 'fake-host',
        private readonly string $dbName = 'fakedb',
    ) {
    }

    public function connect(): PDO
    {
        $this->connectCalls++;

        return $this->pdo;
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
