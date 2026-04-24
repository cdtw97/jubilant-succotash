<?php
declare(strict_types=1);

namespace MyFrancis\Http;

use JsonException;
use MyFrancis\Core\Enums\ContentType;
use MyFrancis\Core\Enums\HttpStatus;
use MyFrancis\Core\Response;

final class JsonResponse extends Response
{
    private const API_VERSION = 'v1';

    /**
     * @param array<string, string> $headers
     */
    public static function success(
        mixed $data,
        string $requestId,
        string $apiVersion = self::API_VERSION,
        HttpStatus|int $status = HttpStatus::OK,
        array $headers = [],
    ): self {
        return self::payload([
            'data' => $data,
            'meta' => [
                'request_id' => $requestId,
                'api_version' => $apiVersion,
            ],
        ], $status, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function error(
        string $code,
        string $message,
        string $requestId,
        HttpStatus|int $status = HttpStatus::BAD_REQUEST,
        array $headers = [],
    ): self {
        return self::payload([
            'error' => [
                'code' => $code,
                'message' => $message,
                'request_id' => $requestId,
            ],
        ], $status, $headers);
    }

    /**
     * @param array<int|string, mixed> $payload
     * @param array<string, string> $headers
     */
    private static function payload(array $payload, HttpStatus|int $status, array $headers): self
    {
        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            $body = '{"error":{"code":"encoding_failure","message":"Unable to encode the response.","request_id":""}}';
        }

        return new self(
            $status instanceof HttpStatus ? $status->value : $status,
            ['Content-Type' => ContentType::JSON->value, ...$headers],
            $body,
        );
    }
}
