<?php
declare(strict_types=1);

namespace MyFrancis\Core;

use JsonException;
use MyFrancis\Core\Enums\ContentType;
use MyFrancis\Core\Enums\HttpStatus;

class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        protected int $statusCode = HttpStatus::OK->value,
        protected array $headers = [],
        protected string $body = '',
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public static function html(
        string $html,
        HttpStatus|int $status = HttpStatus::OK,
        array $headers = [],
    ): self {
        return new self(
            self::normalizeStatus($status),
            ['Content-Type' => ContentType::HTML->value, ...$headers],
            $html,
        );
    }

    /**
     * @param array<int|string, mixed> $payload
     * @param array<string, string> $headers
     */
    public static function json(
        array $payload,
        HttpStatus|int $status = HttpStatus::OK,
        array $headers = [],
    ): self {
        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            $body = '{"error":{"code":"encoding_failure","message":"Unable to encode the response.","request_id":""}}';
        }

        return new self(
            self::normalizeStatus($status),
            ['Content-Type' => ContentType::JSON->value, ...$headers],
            $body,
        );
    }

    /**
     * @param array<string, string> $headers
     */
    public static function redirect(
        string $location,
        HttpStatus|int $status = HttpStatus::FOUND,
        array $headers = [],
    ): self {
        $safeLocation = str_replace(["\r", "\n"], '', $location);

        return new self(
            self::normalizeStatus($status),
            ['Location' => $safeLocation, ...$headers],
            '',
        );
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function withStatus(HttpStatus|int $status): static
    {
        $clone = clone $this;
        $clone->statusCode = self::normalizeStatus($status);

        return $clone;
    }

    public function withHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->headers[$this->sanitizeHeaderPart($name)] = $this->sanitizeHeaderPart($value);

        return $clone;
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): static
    {
        $clone = clone $this;

        foreach ($headers as $name => $value) {
            $clone->headers[$this->sanitizeHeaderPart($name)] = $this->sanitizeHeaderPart($value);
        }

        return $clone;
    }

    public function withBody(string $body): static
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    public function send(): void
    {
        if (! headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $value) {
                header(sprintf('%s: %s', $name, $value), true);
            }
        }

        echo $this->body;
    }

    protected static function normalizeStatus(HttpStatus|int $status): int
    {
        return $status instanceof HttpStatus ? $status->value : $status;
    }

    private function sanitizeHeaderPart(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }
}
