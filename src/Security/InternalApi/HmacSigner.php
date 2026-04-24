<?php
declare(strict_types=1);

namespace MyFrancis\Security\InternalApi;

use DateTimeImmutable;
use DateTimeZone;
use MyFrancis\Core\Request;
use Stringable;

final class HmacSigner
{
    /**
     * @param array<int|string, mixed> $queryParameters
     */
    public function sign(
        string $method,
        string $path,
        array $queryParameters,
        string $timestamp,
        string $nonce,
        string $rawBody,
        string $sharedSecret,
    ): string {
        $canonicalRequest = $this->canonicalRequestString(
            $method,
            $path,
            $queryParameters,
            $timestamp,
            $nonce,
            $rawBody,
        );

        return $this->signCanonicalRequest($canonicalRequest, $sharedSecret);
    }

    public function signCanonicalRequest(string $canonicalRequest, string $sharedSecret): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $canonicalRequest, $sharedSecret, true));
    }

    /**
     * @param array<int|string, mixed> $queryParameters
     */
    public function canonicalRequestString(
        string $method,
        string $path,
        array $queryParameters,
        string $timestamp,
        string $nonce,
        string $rawBody,
    ): string {
        return implode("\n", [
            strtoupper(trim($method)),
            $path === '' ? '/' : $path,
            $this->canonicalSortedQueryString($queryParameters),
            $timestamp,
            $nonce,
            $this->rawBodyHash($rawBody),
        ]);
    }

    /**
     * @param array<int|string, mixed> $queryParameters
     */
    public function canonicalSortedQueryString(array $queryParameters): string
    {
        if ($queryParameters === []) {
            return '';
        }

        $normalized = $this->normalizeQueryArray($queryParameters);

        return http_build_query($normalized, '', '&', PHP_QUERY_RFC3986);
    }

    public function rawBodyHash(string $rawBody): string
    {
        return hash('sha256', $rawBody);
    }

    /**
     * @return array<string, string>
     */
    public function buildSignedHeaders(
        Request $request,
        string $appId,
        string $keyId,
        string $sharedSecret,
        ?DateTimeImmutable $timestamp = null,
        ?string $nonce = null,
        ?string $requestId = null,
    ): array {
        $timestamp = ($timestamp ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'));
        $timestampValue = $timestamp->format('Y-m-d\TH:i:s\Z');
        $nonce ??= $this->generateNonce();
        $requestId ??= Request::generateRequestId();

        return [
            'X-MF-App' => $appId,
            'X-MF-Key-Id' => $keyId,
            'X-MF-Timestamp' => $timestampValue,
            'X-MF-Nonce' => $nonce,
            'X-MF-Request-Id' => $requestId,
            'X-MF-Signature' => $this->sign(
                $request->method(),
                $request->path(),
                is_array($request->query()) ? $request->query() : [],
                $timestampValue,
                $nonce,
                $request->rawBody(),
                $sharedSecret,
            ),
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ];
    }

    public function generateNonce(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    /**
     * @param array<int|string, mixed> $queryParameters
     * @return array<int|string, mixed>
     */
    private function normalizeQueryArray(array $queryParameters): array
    {
        if (! array_is_list($queryParameters)) {
            ksort($queryParameters);
        }

        foreach ($queryParameters as $key => $value) {
            if (is_array($value)) {
                $queryParameters[$key] = $this->normalizeQueryArray($value);
                continue;
            }

            $queryParameters[$key] = $this->stringValue($value);
        }

        return $queryParameters;
    }

    private function base64UrlEncode(string $payload): string
    {
        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return '';
    }
}
