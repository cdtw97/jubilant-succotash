<?php
declare(strict_types=1);

namespace MyFrancis\Core;

use Closure;
use MyFrancis\Core\Enums\HttpMethod;
use MyFrancis\Http\Middleware\MiddlewareInterface;
use Stringable;

/**
 * @phpstan-type ControllerAction array{0: class-string, 1: non-empty-string}
 */
readonly class Route
{
    /**
     * @param Closure|ControllerAction $action
     * @param list<Closure|string|MiddlewareInterface> $middleware
     * @param array<string, string> $constraints
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public HttpMethod $method,
        public string $path,
        public Closure|array $action,
        public ?string $name = null,
        public array $middleware = [],
        public array $constraints = [],
        public array $attributes = [],
    ) {
    }

    /**
     * @return array<string, string>|null
     */
    public function match(string $path): ?array
    {
        /** @var list<string> $parameterNames */
        $parameterNames = [];
        $regex = $this->compileRegex($parameterNames);

        if (preg_match($regex, self::normalizePath($path), $matches) !== 1) {
            return null;
        }

        $parameters = [];

        foreach ($parameterNames as $parameterName) {
            if (! array_key_exists($parameterName, $matches)) {
                continue;
            }

            $parameters[$parameterName] = $matches[$parameterName];
        }

        return $parameters;
    }

    /**
     * @param array<string, bool|float|int|string|Stringable|null> $parameters
     */
    public function uri(array $parameters = []): string
    {
        return preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*)(?::[^}]+)?\}/',
            static function (array $matches) use ($parameters): string {
                $parameterName = $matches[1];

                return array_key_exists($parameterName, $parameters)
                    ? rawurlencode(self::parameterValueToString($parameters[$parameterName]))
                    : $matches[0];
            },
            $this->normalizedPath(),
        ) ?? $this->normalizedPath();
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    private function normalizedPath(): string
    {
        return self::normalizePath($this->path);
    }

    /**
     * @param list<string> $parameterNames
     */
    private function compileRegex(array &$parameterNames): string
    {
        $normalizedPath = $this->normalizedPath();

        if ($normalizedPath === '/') {
            return '#^/$#';
        }

        $segments = explode('/', trim($normalizedPath, '/'));
        $regexSegments = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)(?::([^}]+))?\}$/', $segment, $matches) === 1) {
                $parameterName = $matches[1];
                $parameterNames[] = $parameterName;

                $constraint = $this->constraints[$parameterName] ?? ($matches[2] ?? '[^/]+');
                $regexSegments[] = sprintf('(?P<%s>%s)', $parameterName, $constraint);
                continue;
            }

            $regexSegments[] = preg_quote($segment, '#');
        }

        return '#^/' . implode('/', $regexSegments) . '$#';
    }

    private static function normalizePath(string $path): string
    {
        $trimmed = trim($path);

        if ($trimmed === '' || $trimmed === '/') {
            return '/';
        }

        $normalized = '/' . trim($trimmed, '/');

        return preg_replace('#/+#', '/', $normalized) ?? '/';
    }

    private static function parameterValueToString(bool|float|int|string|Stringable|null $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
