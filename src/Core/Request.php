<?php
declare(strict_types=1);

namespace MyFrancis\Core;

use Stringable;

readonly class Request
{
    private string $method;
    private string $path;

    /** @var array<int|string, mixed> */
    private array $queryParameters;

    /** @var array<int|string, mixed> */
    private array $inputParameters;

    /** @var array<int|string, mixed> */
    private array $jsonPayload;

    /** @var array<string, string> */
    private array $headers;

    /** @var array<int|string, mixed> */
    private array $cookies;

    /** @var array<int|string, mixed> */
    private array $uploadedFiles;

    /** @var array<int|string, mixed> */
    private array $serverParameters;

    private string $ipAddress;
    private string $requestIdentifier;
    private string $rawBody;

    /** @var array<int|string, mixed> */
    private array $attributes;

    /**
     * @param array<int|string, mixed> $queryParameters
     * @param array<int|string, mixed> $inputParameters
     * @param array<int|string, mixed> $jsonPayload
     * @param array<int|string, mixed> $headers
     * @param array<int|string, mixed> $cookies
     * @param array<int|string, mixed> $uploadedFiles
     * @param array<int|string, mixed> $serverParameters
     * @param array<int|string, mixed> $attributes
     */
    public function __construct(
        string $method,
        string $path,
        array $queryParameters = [],
        array $inputParameters = [],
        array $jsonPayload = [],
        array $headers = [],
        array $cookies = [],
        array $uploadedFiles = [],
        array $serverParameters = [],
        string $ipAddress = '0.0.0.0',
        string $requestIdentifier = '',
        string $rawBody = '',
        array $attributes = [],
    ) {
        $this->method = strtoupper(trim($method));
        $this->path = self::normalizePath($path);
        $this->queryParameters = $queryParameters;
        $this->inputParameters = $inputParameters;
        $this->jsonPayload = $jsonPayload;
        $this->requestIdentifier = $requestIdentifier !== ''
            ? self::normalizeRequestId($requestIdentifier)
            : self::generateRequestId();
        $this->headers = self::normalizeHeaders($headers);
        $this->cookies = $cookies;
        $this->uploadedFiles = $uploadedFiles;
        $this->serverParameters = $serverParameters;
        $this->ipAddress = $ipAddress;
        $this->rawBody = $rawBody;
        $this->attributes = $attributes;
    }

    public static function capture(): self
    {
        $headers = self::captureHeaders();
        $rawBody = file_get_contents('php://input');

        return new self(
            method: self::stringFromArray($_SERVER, 'REQUEST_METHOD', 'GET'),
            path: self::pathFromGlobals(),
            queryParameters: $_GET,
            inputParameters: $_POST,
            jsonPayload: [],
            headers: $headers,
            cookies: $_COOKIE,
            uploadedFiles: $_FILES,
            serverParameters: $_SERVER,
            ipAddress: self::stringFromArray($_SERVER, 'REMOTE_ADDR', '0.0.0.0'),
            requestIdentifier: $headers['x-request-id'] ?? '',
            rawBody: $rawBody === false ? '' : $rawBody,
            attributes: [],
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryParameters;
        }

        return $this->queryParameters[$key] ?? $default;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        $input = array_replace($this->jsonPayload, $this->inputParameters);

        if ($key === null) {
            return $input;
        }

        return $input[$key] ?? $default;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->jsonPayload;
        }

        return $this->jsonPayload[$key] ?? $default;
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function cookie(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->cookies;
        }

        return $this->cookies[$key] ?? $default;
    }

    public function files(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->uploadedFiles;
        }

        return $this->uploadedFiles[$key] ?? $default;
    }

    public function server(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->serverParameters;
        }

        return $this->serverParameters[$key] ?? $default;
    }

    public function ip(): string
    {
        return $this->ipAddress;
    }

    public function requestId(): string
    {
        return $this->requestIdentifier;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * @return array<int|string, mixed>
    */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function isSecure(): bool
    {
        $https = strtolower(self::stringValue($this->serverParameters['HTTPS'] ?? ''));
        $forwardedProto = strtolower(self::stringValue($this->header('x-forwarded-proto', '')));
        $requestScheme = strtolower(self::stringValue($this->serverParameters['REQUEST_SCHEME'] ?? ''));
        $serverPort = self::stringValue($this->serverParameters['SERVER_PORT'] ?? '');

        return $https === 'on'
            || $https === '1'
            || $forwardedProto === 'https'
            || $requestScheme === 'https'
            || $serverPort === '443';
    }

    public function withRequestId(string $requestId): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->queryParameters,
            $this->inputParameters,
            $this->jsonPayload,
            $this->headers,
            $this->cookies,
            $this->uploadedFiles,
            $this->serverParameters,
            $this->ipAddress,
            $requestId,
            $this->rawBody,
            $this->attributes,
        );
    }

    /**
     * @param array<int|string, mixed> $jsonPayload
     */
    public function withJsonPayload(array $jsonPayload): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->queryParameters,
            $this->inputParameters,
            $jsonPayload,
            $this->headers,
            $this->cookies,
            $this->uploadedFiles,
            $this->serverParameters,
            $this->ipAddress,
            $this->requestIdentifier,
            $this->rawBody,
            $this->attributes,
        );
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$key] = $value;

        return new self(
            $this->method,
            $this->path,
            $this->queryParameters,
            $this->inputParameters,
            $this->jsonPayload,
            $this->headers,
            $this->cookies,
            $this->uploadedFiles,
            $this->serverParameters,
            $this->ipAddress,
            $this->requestIdentifier,
            $this->rawBody,
            $attributes,
        );
    }

    public static function generateRequestId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function isValidRequestId(string $requestId): bool
    {
        return preg_match('/^[A-Za-z0-9._-]{8,128}$/', $requestId) === 1;
    }

    private static function pathFromGlobals(): string
    {
        $uri = self::stringFromArray($_SERVER, 'REQUEST_URI', '/');
        $path = (string) parse_url($uri, PHP_URL_PATH);

        $scriptName = str_replace('\\', '/', self::stringFromArray($_SERVER, 'SCRIPT_NAME', '/index.php'));
        $scriptDirectory = str_replace('\\', '/', dirname($scriptName));

        if ($scriptDirectory !== '/' && $scriptDirectory !== '.' && $scriptDirectory !== '\\' && str_starts_with($path, $scriptDirectory)) {
            $path = substr($path, strlen($scriptDirectory)) ?: '/';
        }

        return self::normalizePath($path);
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

    /**
     * @param array<int|string, mixed> $headers
     * @return array<string, string>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $key => $value) {
            $stringValue = self::stringableValue($value);

            if ($stringValue === null) {
                continue;
            }

            $normalized[strtolower((string) $key)] = $stringValue;
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private static function captureHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return self::normalizeHeaders(getallheaders());
        }

        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $normalizedKey = strtolower(str_replace('_', '-', substr($key, 5)));
                $headerValue = self::stringableValue($value);

                if ($headerValue !== null) {
                    $headers[$normalizedKey] = $headerValue;
                }

                continue;
            }

            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                $headerValue = self::stringableValue($value);

                if ($headerValue !== null) {
                    $headers[strtolower(str_replace('_', '-', $key))] = $headerValue;
                }
            }
        }

        return $headers;
    }

    private static function normalizeRequestId(string $requestId): string
    {
        return self::isValidRequestId($requestId)
            ? $requestId
            : self::generateRequestId();
    }

    /**
     * @param array<int|string, mixed> $source
     */
    private static function stringFromArray(array $source, string $key, string $default = ''): string
    {
        return self::stringValue($source[$key] ?? $default);
    }

    private static function stringValue(mixed $value): string
    {
        return self::stringableValue($value) ?? '';
    }

    private static function stringableValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return null;
    }
}
