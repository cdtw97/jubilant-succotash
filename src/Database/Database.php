<?php
declare(strict_types=1);

namespace MyFrancis\Database;

use MyFrancis\Config\DatabaseConfig;
use PDO;
use PDOStatement;
use Throwable;

final class Database
{
    private readonly PDO $pdo;

    public function __construct(DatabaseConfig $config, ?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? new PDO($config->dsn(), $config->username, $config->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ]);
    }

    /**
     * SQL identifiers such as column names and table names cannot be parameter-bound.
     * Always allowlist identifiers before interpolating them into SQL.
     *
     * @param array<int|string, mixed> $params
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);

        foreach ($params as $parameter => $value) {
            $position = is_int($parameter) ? $parameter + 1 : $parameter;
            $statement->bindValue($position, $value, $this->detectPdoType($value));
        }

        $statement->execute();

        return $statement;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $rows = $this->query($sql, $params)->fetchAll();
        $result = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $normalizedRow = [];

                foreach ($row as $column => $value) {
                    if (is_string($column)) {
                        $normalizedRow[$column] = $value;
                    }
                }

                $result[] = $normalizedRow;
            }
        }

        return $result;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();

        if (! is_array($result)) {
            return null;
        }

        $normalizedRow = [];

        foreach ($result as $column => $value) {
            if (is_string($column)) {
                $normalizedRow[$column] = $value;
            }
        }

        return $normalizedRow;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * @param callable(self): mixed $callback
     */
    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function lastInsertId(): string
    {
        $lastInsertId = $this->pdo->lastInsertId();

        return $lastInsertId === false ? '' : $lastInsertId;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function detectPdoType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }
}
