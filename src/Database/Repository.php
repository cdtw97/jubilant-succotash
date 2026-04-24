<?php
declare(strict_types=1);

namespace MyFrancis\Database;

abstract class Repository
{
    public function __construct(protected readonly Database $database)
    {
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    protected function fetchAll(string $sql, array $params = []): array
    {
        return $this->database->fetchAll($sql, $params);
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function fetchOne(string $sql, array $params = []): ?array
    {
        return $this->database->fetchOne($sql, $params);
    }

    /**
     * @param array<int|string, mixed> $params
     */
    protected function execute(string $sql, array $params = []): int
    {
        return $this->database->execute($sql, $params);
    }
}
