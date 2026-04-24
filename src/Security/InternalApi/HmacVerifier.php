<?php
declare(strict_types=1);

namespace MyFrancis\Security\InternalApi;

use DateTimeImmutable;
use DateTimeZone;
use MyFrancis\Config\AppConfig;
use MyFrancis\Core\Exceptions\ForbiddenHttpException;
use MyFrancis\Core\Exceptions\UnauthorizedHttpException;
use MyFrancis\Core\Request;

final class HmacVerifier
{
    private const MAX_CLOCK_SKEW_SECONDS = 300;

    public function __construct(
        private readonly AppConfig $appConfig,
        private readonly HmacSigner $hmacSigner,
        private readonly NonceStore $nonceStore,
    ) {
    }

    public function verify(Request $request, InternalScope $requiredScope): InternalApiRequestContext
    {
        $appId = $this->requiredHeader($request, 'x-mf-app');
        $keyId = $this->requiredHeader($request, 'x-mf-key-id');
        $timestampHeader = $this->requiredHeader($request, 'x-mf-timestamp');
        $nonce = $this->requiredHeader($request, 'x-mf-nonce');
        $signature = $this->requiredHeader($request, 'x-mf-signature');
        $requestId = $this->requiredHeader($request, 'x-mf-request-id');

        if ($requestId !== $request->requestId()) {
            throw new UnauthorizedHttpException('The internal API request id is invalid.');
        }

        $this->assertCredentials($appId, $keyId);
        $this->assertNonce($nonce);
        $timestamp = $this->parseTimestamp($timestampHeader);
        $this->assertFreshTimestamp($timestamp);

        $expectedSignature = $this->hmacSigner->sign(
            $request->method(),
            $request->path(),
            is_array($request->query()) ? $request->query() : [],
            $timestampHeader,
            $nonce,
            $request->rawBody(),
            $this->appConfig->internalApiSecret,
        );

        if (! hash_equals($expectedSignature, $signature)) {
            throw new UnauthorizedHttpException('The internal API signature is invalid.');
        }

        if (! in_array($requiredScope->value, $this->appConfig->internalApiScopes, true)) {
            throw new ForbiddenHttpException('The configured internal API key is not authorized for this scope.');
        }

        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+' . self::MAX_CLOCK_SKEW_SECONDS . ' seconds');

        if (! $this->nonceStore->remember('internal-api:' . $appId . ':' . $keyId . ':' . $nonce, $expiresAt)) {
            throw new UnauthorizedHttpException('Replay protection rejected this internal API request.');
        }

        return new InternalApiRequestContext(
            $appId,
            $keyId,
            $requestId,
            $nonce,
            $signature,
            $this->hmacSigner->rawBodyHash($request->rawBody()),
            $timestamp,
            $requiredScope,
            $this->appConfig->internalApiScopes,
        );
    }

    private function requiredHeader(Request $request, string $name): string
    {
        $value = $request->header($name);

        if (! is_string($value) || trim($value) === '') {
            throw new UnauthorizedHttpException(sprintf('The %s header is required.', $this->displayHeaderName($name)));
        }

        return trim($value);
    }

    private function assertCredentials(string $appId, string $keyId): void
    {
        if ($appId !== $this->appConfig->internalApiAppId || $keyId !== $this->appConfig->internalApiKeyId) {
            throw new UnauthorizedHttpException('The internal API credentials are invalid.');
        }
    }

    private function assertNonce(string $nonce): void
    {
        if (preg_match('/^[A-Za-z0-9._-]{16,128}$/', $nonce) !== 1) {
            throw new UnauthorizedHttpException('The internal API nonce is invalid.');
        }
    }

    private function parseTimestamp(string $timestamp): DateTimeImmutable
    {
        try {
            return (new DateTimeImmutable($timestamp))->setTimezone(new DateTimeZone('UTC'));
        } catch (\Exception) {
            throw new UnauthorizedHttpException('The internal API timestamp is invalid.');
        }
    }

    private function assertFreshTimestamp(DateTimeImmutable $timestamp): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $difference = abs($now->getTimestamp() - $timestamp->getTimestamp());

        if ($difference > self::MAX_CLOCK_SKEW_SECONDS) {
            throw new UnauthorizedHttpException('The internal API timestamp is outside the allowed time window.');
        }
    }

    private function displayHeaderName(string $headerName): string
    {
        return implode('-', array_map(
            static fn (string $segment): string => strtoupper($segment),
            explode('-', $headerName),
        ));
    }
}
