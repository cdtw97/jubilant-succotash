<?php
declare(strict_types=1);

namespace MyFrancis\Security\InternalApi;

use DateTimeImmutable;

final class NonceStore
{
    public function __construct(private readonly string $directory)
    {
    }

    public function remember(string $key, DateTimeImmutable $expiresAt): bool
    {
        $this->ensureDirectory();

        $path = $this->pathFor($key);
        $handle = fopen($path, 'c+b');

        if ($handle === false) {
            throw new \RuntimeException('Nonce storage could not be opened.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Nonce storage could not be locked.');
            }

            $contents = stream_get_contents($handle);
            $payload = is_string($contents) && $contents !== ''
                ? json_decode($contents, true)
                : null;
            $now = time();

            if (is_array($payload) && isset($payload['expires_at'])) {
                $expiresAtValue = $payload['expires_at'];

                if (is_int($expiresAtValue) && $expiresAtValue >= $now) {
                    return false;
                }

                if (is_string($expiresAtValue) && ctype_digit($expiresAtValue) && (int) $expiresAtValue >= $now) {
                    return false;
                }
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode([
                'expires_at' => $expiresAt->getTimestamp(),
            ], JSON_THROW_ON_ERROR));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return true;
    }

    public function clear(): void
    {
        if (! is_dir($this->directory)) {
            return;
        }

        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function ensureDirectory(): void
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0775, true) && ! is_dir($this->directory)) {
            throw new \RuntimeException('Nonce storage directory could not be initialized.');
        }
    }

    private function pathFor(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    }
}
