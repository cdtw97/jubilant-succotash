<?php
declare(strict_types=1);

namespace App\Repositories;

use MyFrancis\Database\Repository;

final class UserRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $userId): ?array
    {
        return $this->fetchOne(
            'SELECT id, username, email, password_hash, created_at
             FROM users
             WHERE id = :id
             LIMIT 1',
            [':id' => $userId],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForLogin(string $identity): ?array
    {
        return $this->fetchOne(
            'SELECT id, username, email, password_hash, created_at
             FROM users
             WHERE LOWER(email) = LOWER(:email_identity)
                OR LOWER(username) = LOWER(:username_identity)
             LIMIT 1',
            [
                ':email_identity' => $identity,
                ':username_identity' => $identity,
            ],
        );
    }

    public function usernameExists(string $username): bool
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS aggregate
             FROM users
             WHERE LOWER(username) = LOWER(:username)',
            [':username' => $username],
        );

        return (int) ($row['aggregate'] ?? 0) > 0;
    }

    public function emailExists(string $email): bool
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS aggregate
             FROM users
             WHERE LOWER(email) = LOWER(:email)',
            [':email' => $email],
        );

        return (int) ($row['aggregate'] ?? 0) > 0;
    }

    public function create(string $username, string $email, string $passwordHash): int
    {
        $this->execute(
            'INSERT INTO users (username, email, password_hash, created_at)
             VALUES (:username, :email, :password_hash, CURRENT_TIMESTAMP)',
            [
                ':username' => $username,
                ':email' => $email,
                ':password_hash' => $passwordHash,
            ],
        );

        return (int) $this->database->lastInsertId();
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $this->execute(
            'UPDATE users
             SET password_hash = :password_hash
             WHERE id = :id',
            [
                ':password_hash' => $passwordHash,
                ':id' => $userId,
            ],
        );
    }
}
