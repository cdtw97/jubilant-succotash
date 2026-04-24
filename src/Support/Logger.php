<?php
declare(strict_types=1);

namespace MyFrancis\Support;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;

final class Logger
{
    private const REDACTED = '[REDACTED]';

    public function __construct(private readonly string $logFile)
    {
    }

    /**
     * @param array<int|string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * @param array<int|string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * @param array<int|string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * @param array<int|string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param array<int|string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $entry = [
            'timestamp' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $this->sanitizeContext($context),
        ];

        try {
            $payload = json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            $payload = '{"timestamp":"","level":"ERROR","message":"Log encoding failure.","context":{}}';
        }

        $this->writeLine($payload . PHP_EOL);
    }

    public function logFile(): string
    {
        return $this->logFile;
    }

    private function writeLine(string $line): void
    {
        $directory = dirname($this->logFile);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            error_log(trim($line));
            return;
        }

        if (@file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log(trim($line));
        }
    }

    private function sanitizeContext(mixed $value, ?string $key = null): mixed
    {
        if ($this->shouldRedact($key)) {
            return self::REDACTED;
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $itemKey => $itemValue) {
                $sanitized[$itemKey] = $this->sanitizeContext($itemValue, (string) $itemKey);
            }

            return $sanitized;
        }

        if (is_object($value)) {
            return $this->sanitizeContext(get_object_vars($value), $key);
        }

        if (is_string($value)) {
            return $this->sanitizeString($value, $key);
        }

        return $value;
    }

    private function sanitizeString(string $value, ?string $key = null): string
    {
        if ($this->shouldRedact($key)) {
            return self::REDACTED;
        }

        $trimmed = trim($value);

        if (preg_match('/^(bearer|basic)\s+/i', $trimmed) === 1) {
            return self::REDACTED;
        }

        if (str_contains(strtolower($trimmed), 'authorization:')) {
            return self::REDACTED;
        }

        return $trimmed;
    }

    private function shouldRedact(?string $key): bool
    {
        if ($key === null || $key === '') {
            return false;
        }

        $normalizedKey = strtolower(str_replace(['-', ' '], '_', $key));
        $needles = [
            'password',
            'pass',
            'token',
            'signature',
            'cookie',
            'authorization',
            'db_password',
            'internal_api_secret',
            'x_mf_signature',
            'secret',
        ];

        foreach ($needles as $needle) {
            if (str_contains($normalizedKey, $needle)) {
                return true;
            }
        }

        return false;
    }
}
