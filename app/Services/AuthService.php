<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use RuntimeException;
use MyFrancis\Security\SessionManager;

final class AuthService
{
    private const SESSION_USER_ID = 'auth.user_id';
    private const SESSION_INTENDED_PATH = 'auth.intended_path';

    private bool $resolvedCurrentUser = false;

    /** @var array<string, mixed>|null */
    private ?array $currentUser = null;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SessionManager $sessionManager,
    ) {
    }

    public function check(): bool
    {
        return $this->currentUser() !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentUser(): ?array
    {
        if ($this->resolvedCurrentUser) {
            return $this->currentUser;
        }

        $userId = $this->userId();

        if ($userId === null) {
            $this->resolvedCurrentUser = true;
            $this->currentUser = null;

            return null;
        }

        $user = $this->userRepository->findById($userId);

        if ($user === null) {
            $this->sessionManager->forget(self::SESSION_USER_ID);
            $this->resolvedCurrentUser = true;
            $this->currentUser = null;

            return null;
        }

        $this->resolvedCurrentUser = true;
        $this->currentUser = $this->sanitizeUser($user);

        return $this->currentUser;
    }

    public function userId(): ?int
    {
        $storedUserId = $this->sessionManager->get(self::SESSION_USER_ID);

        if (is_int($storedUserId) && $storedUserId > 0) {
            return $storedUserId;
        }

        if (is_string($storedUserId) && ctype_digit($storedUserId) && (int) $storedUserId > 0) {
            return (int) $storedUserId;
        }

        return null;
    }

    public function attempt(string $identity, string $password): bool
    {
        $user = $this->userRepository->findForLogin($identity);

        if ($user === null) {
            return false;
        }

        $passwordHash = $user['password_hash'] ?? null;

        if (! is_string($passwordHash) || $passwordHash === '') {
            return false;
        }

        if (! password_verify($password, $passwordHash)) {
            return false;
        }

        $userId = (int) ($user['id'] ?? 0);

        if ($userId <= 0) {
            return false;
        }

        if (password_needs_rehash($passwordHash, PASSWORD_DEFAULT)) {
            $rehash = password_hash($password, PASSWORD_DEFAULT);

            if (is_string($rehash) && $rehash !== '') {
                $this->userRepository->updatePasswordHash($userId, $rehash);
            }
        }

        $this->logInUser($userId);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function register(string $username, string $email, string $password): array
    {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if (! is_string($passwordHash) || $passwordHash === '') {
            throw new RuntimeException('Unable to hash the provided password.');
        }

        $userId = $this->userRepository->create($username, $email, $passwordHash);
        $this->logInUser($userId);

        $user = $this->currentUser();

        if ($user === null) {
            throw new RuntimeException('The newly created account could not be loaded.');
        }

        return $user;
    }

    public function logout(): void
    {
        $this->sessionManager->destroy();
        $this->sessionManager->start();
        $this->sessionManager->regenerateId(true);
        $this->resetResolvedUser();
    }

    public function usernameExists(string $username): bool
    {
        return $this->userRepository->usernameExists($username);
    }

    public function emailExists(string $email): bool
    {
        return $this->userRepository->emailExists($email);
    }

    public function rememberIntendedPath(string $path): void
    {
        $normalizedPath = trim($path);

        if ($normalizedPath === '' || $normalizedPath === '/login' || $normalizedPath === '/register') {
            return;
        }

        if (! str_starts_with($normalizedPath, '/')) {
            return;
        }

        $this->sessionManager->put(self::SESSION_INTENDED_PATH, $normalizedPath);
    }

    public function pullIntendedPath(string $fallback): string
    {
        $intendedPath = $this->sessionManager->get(self::SESSION_INTENDED_PATH);
        $this->sessionManager->forget(self::SESSION_INTENDED_PATH);

        if (! is_string($intendedPath) || $intendedPath === '' || ! str_starts_with($intendedPath, '/')) {
            return $fallback;
        }

        return $intendedPath;
    }

    private function logInUser(int $userId): void
    {
        $this->sessionManager->regenerateId(true);
        $this->sessionManager->put(self::SESSION_USER_ID, $userId);
        $this->resetResolvedUser();
        $this->currentUser();
    }

    private function resetResolvedUser(): void
    {
        $this->resolvedCurrentUser = false;
        $this->currentUser = null;
    }

    /**
     * @param array<string, mixed> $user
     * @return array{id: int, username: string, email: string, created_at: string}
     */
    private function sanitizeUser(array $user): array
    {
        return [
            'id' => (int) ($user['id'] ?? 0),
            'username' => (string) ($user['username'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'created_at' => (string) ($user['created_at'] ?? ''),
        ];
    }
}
