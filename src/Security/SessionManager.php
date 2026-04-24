<?php
declare(strict_types=1);

namespace MyFrancis\Security;

use MyFrancis\Config\AppConfig;
use MyFrancis\Security\Enums\SameSite;

final class SessionManager
{
    private const SESSION_NAMESPACE = '_myfrancis';
    private const FLASH_NAMESPACE = '_flash';

    public function __construct(
        private readonly AppConfig $appConfig,
        private readonly string $savePath,
        private readonly SameSite $sameSite = SameSite::Lax,
    ) {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $this->configure();
        session_start();
        $this->ensureStore();
    }

    public function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function regenerateId(bool $deleteOldSession = true): void
    {
        $this->start();
        session_regenerate_id($deleteOldSession);
    }

    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $this->sessionStore());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $this->sessionStore()[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->start();
        $store = $this->sessionStore();
        $store[$key] = $value;
        $_SESSION[self::SESSION_NAMESPACE] = $store;
    }

    public function forget(string $key): void
    {
        $this->start();
        $store = $this->sessionStore();
        unset($store[$key]);
        $_SESSION[self::SESSION_NAMESPACE] = $store;
    }

    public function flash(string $key, mixed $value): void
    {
        $this->start();
        $store = $this->sessionStore();
        $flash = $this->flashStore($store);
        $flash[$key] = $value;
        $store[self::FLASH_NAMESPACE] = $flash;
        $_SESSION[self::SESSION_NAMESPACE] = $store;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->start();
        $store = $this->sessionStore();
        $flash = $this->flashStore($store);
        $value = $flash[$key] ?? $default;
        unset($flash[$key]);
        $store[self::FLASH_NAMESPACE] = $flash;
        $_SESSION[self::SESSION_NAMESPACE] = $store;

        return $value;
    }

    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies') === '1') {
            $params = session_get_cookie_params();
            $sessionName = session_name();

            if ($sessionName === false) {
                $sessionName = $this->appConfig->sessionName;
            }

            setcookie(
                $sessionName,
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'],
                ],
            );
        }

        session_destroy();
    }

    private function configure(): void
    {
        if (! is_dir($this->savePath) && ! mkdir($this->savePath, 0775, true) && ! is_dir($this->savePath)) {
            throw new \RuntimeException('Session storage could not be initialized.');
        }

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $this->appConfig->sessionSecure ? '1' : '0');
        ini_set('session.cookie_samesite', $this->sameSite->value);
        ini_set('session.use_trans_sid', '0');
        ini_set('session.save_path', $this->savePath);

        session_name($this->appConfig->sessionName);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $this->appConfig->sessionSecure,
            'httponly' => true,
            'samesite' => $this->sameSite->value,
        ]);
    }

    private function ensureStore(): void
    {
        if (! isset($_SESSION[self::SESSION_NAMESPACE]) || ! is_array($_SESSION[self::SESSION_NAMESPACE])) {
            $_SESSION[self::SESSION_NAMESPACE] = [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionStore(): array
    {
        $this->ensureStore();
        $store = $_SESSION[self::SESSION_NAMESPACE] ?? [];

        if (! is_array($store)) {
            return [];
        }

        $normalizedStore = [];

        foreach ($store as $key => $value) {
            if (is_string($key)) {
                $normalizedStore[$key] = $value;
            }
        }

        return $normalizedStore;
    }

    /**
     * @param array<string, mixed> $store
     * @return array<string, mixed>
     */
    private function flashStore(array $store): array
    {
        $flash = $store[self::FLASH_NAMESPACE] ?? [];

        if (! is_array($flash)) {
            return [];
        }

        $normalizedFlash = [];

        foreach ($flash as $key => $value) {
            if (is_string($key)) {
                $normalizedFlash[$key] = $value;
            }
        }

        return $normalizedFlash;
    }
}
