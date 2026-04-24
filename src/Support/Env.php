<?php
declare(strict_types=1);

namespace MyFrancis\Support;

final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $primaryPath, ?string $fallbackPath = null): void
    {
        if ($fallbackPath !== null && is_file($fallbackPath)) {
            self::$values = array_merge(self::$values, self::parseFile($fallbackPath));
        }

        if (is_file($primaryPath)) {
            self::$values = array_merge(self::$values, self::parseFile($primaryPath));
        }
    }

    public static function reset(): void
    {
        self::$values = [];
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $environmentValue = getenv($key);

        if ($environmentValue !== false) {
            return $environmentValue;
        }

        return self::$values[$key] ?? $default;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key, $default);

        return is_string($value) ? trim($value) : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT);

        return $validated !== false ? (int) $validated : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $validated = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $validated ?? $default;
    }

    /**
     * @param list<string> $default
     * @return list<string>
     */
    public static function csv(string $key, array $default = []): array
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn (mixed $item): string => trim(self::stringValue($item)),
                $value,
            ), static fn (string $item): bool => $item !== ''));
        }

        $items = explode(',', self::stringValue($value));

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            $items,
        ), static fn (string $item): bool => $item !== ''));
    }

    /**
     * @return array<string, string>
     */
    private static function parseFile(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        $values = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $separatorPosition = strpos($trimmed, '=');

            if ($separatorPosition === false) {
                continue;
            }

            $key = trim(substr($trimmed, 0, $separatorPosition));
            $value = trim(substr($trimmed, $separatorPosition + 1));

            if ($key === '') {
                continue;
            }

            $values[$key] = self::stripQuotes($value);
        }

        return $values;
    }

    private static function stripQuotes(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $firstCharacter = $value[0];
        $lastCharacter = $value[strlen($value) - 1];

        if (($firstCharacter === '"' && $lastCharacter === '"') || ($firstCharacter === "'" && $lastCharacter === "'")) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    private static function stringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return '';
    }
}
