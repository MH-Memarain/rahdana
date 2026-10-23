<?php

declare(strict_types=1);

namespace Paya\Core;

use InvalidArgumentException;
use LogicException;
use PDO;
use RuntimeException;
use Throwable;

/** Small PDO wrapper; SQL and permissions belong to the application models. */
final class Database
{
    private PDO $connection;

    public function __construct(array $config)
    {
        $driver = $config['driver'] ?? 'mysql';
        if ($driver === 'mysql') {
            $database = (string) ($config['database'] ?? '');
            if ($database === '') {
                throw new InvalidArgumentException('Database name is required.');
            }
            $host = (string) ($config['host'] ?? '127.0.0.1');
            $port = (int) ($config['port'] ?? 3306);
            // utf8mb4 is fixed, not derived from untrusted request data.
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
            $options = [PDO::ATTR_EMULATE_PREPARES => false];
            $username = (string) ($config['username'] ?? '');
            $password = (string) ($config['password'] ?? '');
        } elseif ($driver === 'sqlite') {
            $dsn = 'sqlite:' . (string) ($config['database'] ?? ':memory:');
            $options = [];
            $username = null;
            $password = null;
        } else {
            throw new InvalidArgumentException('Unsupported database driver.');
        }

        $this->connection = new PDO($dsn, $username, $password, $options + [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetch();
        return $value === false ? null : $value;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }

    public function lastInsertId(): string
    {
        $value = $this->connection->lastInsertId();
        if ($value === false) {
            throw new RuntimeException('Last insert ID is unavailable.');
        }
        return $value;
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->connection->inTransaction()) {
            throw new LogicException('Nested transactions are not supported.');
        }
        $this->connection->beginTransaction();
        try {
            $result = $callback($this);
            $this->connection->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $error;
        }
    }

    public function pdo(): PDO { return $this->connection; }
}
