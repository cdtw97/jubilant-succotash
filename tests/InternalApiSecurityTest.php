<?php
declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use MyFrancis\Core\Request;
use MyFrancis\Security\InternalApi\HmacSigner;

final class InternalApiSecurityTest extends FrameworkTestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['INTERNAL_API_SCOPES']);

        parent::tearDown();
    }

    public function testValidSignatureIsAccepted(): void
    {
        $application = $this->bootApplication();

        $response = $application->handle($this->signedInternalRequest($application, 'GET', '/internal/v1/health'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('"status":"ok"', $response->body());
    }

    public function testInvalidSignatureIsRejected(): void
    {
        $application = $this->bootApplication();
        $request = $this->signedInternalRequest(
            $application,
            'POST',
            '/internal/v1/session/introspect',
            payload: [
                'session_token_hash' => hash('sha256', 'session-token'),
                'requested_scopes' => ['user:read'],
            ],
            rawBody: '{"session_token_hash":"tampered","requested_scopes":["user:read"]}',
            signingBody: '{"session_token_hash":"original","requested_scopes":["user:read"]}',
        );

        $response = $application->handle($request);

        self::assertSame(401, $response->statusCode());
        self::assertStringContainsString('"unauthorized"', $response->body());
    }

    public function testExpiredTimestampIsRejected(): void
    {
        $application = $this->bootApplication();
        $request = $this->signedInternalRequest(
            $application,
            'GET',
            '/internal/v1/health',
            timestamp: new DateTimeImmutable('-10 minutes'),
        );

        $response = $application->handle($request);

        self::assertSame(401, $response->statusCode());
    }

    public function testTimestampTooFarInFutureIsRejected(): void
    {
        $application = $this->bootApplication();
        $request = $this->signedInternalRequest(
            $application,
            'GET',
            '/internal/v1/health',
            timestamp: new DateTimeImmutable('+10 minutes'),
        );

        $response = $application->handle($request);

        self::assertSame(401, $response->statusCode());
    }

    public function testReusedNonceIsRejected(): void
    {
        $application = $this->bootApplication();
        $nonce = 'nonce_replay_test_1234567890';
        $firstRequest = $this->signedInternalRequest($application, 'GET', '/internal/v1/health', nonce: $nonce);
        $secondRequest = $this->signedInternalRequest($application, 'GET', '/internal/v1/health', nonce: $nonce);

        $firstResponse = $application->handle($firstRequest);
        $secondResponse = $application->handle($secondRequest);

        self::assertSame(200, $firstResponse->statusCode());
        self::assertSame(401, $secondResponse->statusCode());
    }

    public function testMissingRequiredSignatureHeaderIsRejected(): void
    {
        $application = $this->bootApplication();
        $headers = $this->signedInternalHeaders($application, 'GET', '/internal/v1/health');
        unset($headers['X-MF-Signature']);
        $request = new Request('GET', '/internal/v1/health', headers: $headers, serverParameters: ['SCRIPT_NAME' => '/index.php']);

        $response = $application->handle($request);

        self::assertSame(401, $response->statusCode());
    }

    public function testMissingContentTypeIsRejected(): void
    {
        $application = $this->bootApplication();
        $rawBody = '{"session_token_hash":"abc","requested_scopes":["user:read"]}';
        $headers = $this->signedInternalHeaders($application, 'POST', '/internal/v1/session/introspect', $rawBody);
        unset($headers['Content-Type']);
        $request = new Request(
            'POST',
            '/internal/v1/session/introspect',
            headers: $headers,
            rawBody: $rawBody,
            serverParameters: ['SCRIPT_NAME' => '/index.php'],
        );

        $response = $application->handle($request);

        self::assertSame(415, $response->statusCode());
    }

    public function testMissingAcceptHeaderIsRejected(): void
    {
        $application = $this->bootApplication();
        $rawBody = '{"session_token_hash":"abc","requested_scopes":["user:read"]}';
        $headers = $this->signedInternalHeaders($application, 'POST', '/internal/v1/session/introspect', $rawBody);
        unset($headers['Accept']);
        $request = new Request(
            'POST',
            '/internal/v1/session/introspect',
            headers: $headers,
            rawBody: $rawBody,
            serverParameters: ['SCRIPT_NAME' => '/index.php'],
        );

        $response = $application->handle($request);

        self::assertSame(406, $response->statusCode());
    }

    public function testInvalidJsonIsRejectedBeforeControllerRuns(): void
    {
        $application = $this->bootApplication();
        $rawBody = '{"session_token_hash":';
        $headers = $this->signedInternalHeaders($application, 'POST', '/internal/v1/session/introspect', $rawBody);
        $request = new Request(
            'POST',
            '/internal/v1/session/introspect',
            headers: $headers,
            rawBody: $rawBody,
            serverParameters: ['SCRIPT_NAME' => '/index.php'],
        );

        $response = $application->handle($request);

        self::assertSame(400, $response->statusCode());
        self::assertStringContainsString('"bad_request"', $response->body());
    }

    public function testBodyTamperingIsRejected(): void
    {
        $application = $this->bootApplication();
        $originalBody = '{"session_token_hash":"abc","requested_scopes":["user:read"]}';
        $tamperedBody = '{"session_token_hash":"xyz","requested_scopes":["user:read"]}';
        $headers = $this->signedInternalHeaders($application, 'POST', '/internal/v1/session/introspect', $originalBody);
        $request = new Request(
            'POST',
            '/internal/v1/session/introspect',
            headers: $headers,
            rawBody: $tamperedBody,
            serverParameters: ['SCRIPT_NAME' => '/index.php'],
        );

        $response = $application->handle($request);

        self::assertSame(401, $response->statusCode());
    }

    public function testMissingScopeReturnsForbidden(): void
    {
        $application = $this->bootApplication(['INTERNAL_API_SCOPES' => 'health:read']);
        $request = $this->signedInternalRequest($application, 'POST', '/internal/v1/session/introspect', [
            'session_token_hash' => hash('sha256', 'session-token'),
            'requested_scopes' => ['user:read'],
        ]);

        $response = $application->handle($request);

        self::assertSame(403, $response->statusCode());
    }

    public function testCanonicalQuerySortingIsDeterministic(): void
    {
        $signer = new HmacSigner();
        $queryA = ['z' => '9', 'a' => ['beta' => '2', 'alpha' => '1']];
        $queryB = ['a' => ['alpha' => '1', 'beta' => '2'], 'z' => '9'];

        self::assertSame(
            $signer->canonicalSortedQueryString($queryA),
            $signer->canonicalSortedQueryString($queryB),
        );
    }

    public function testBase64UrlSignatureEncodingIsUrlSafeAndUnpadded(): void
    {
        $signer = new HmacSigner();
        $signature = $signer->sign('POST', '/internal/v1/test', [], '2026-04-22T12:00:00Z', 'nonce_1234567890123456', '{}', 'secret');

        self::assertDoesNotMatchRegularExpression('/[+=\\/]/', $signature);
    }

    public function testConstantTimeComparisonIsUsedForSignatureVerification(): void
    {
        $source = (string) file_get_contents($this->basePath . '/src/Security/InternalApi/HmacVerifier.php');

        self::assertStringContainsString('hash_equals', $source);
    }

    public function testInternalApiDoesNotUseBrowserCookiesForAuthentication(): void
    {
        $application = $this->bootApplication();
        $headers = $this->signedInternalHeaders($application, 'GET', '/internal/v1/health');
        $headers['Cookie'] = 'mf_session=abc123';
        $request = new Request(
            'GET',
            '/internal/v1/health',
            headers: $headers,
            cookies: ['mf_session' => 'abc123'],
            serverParameters: ['SCRIPT_NAME' => '/index.php'],
        );

        $response = $application->handle($request);

        self::assertSame(400, $response->statusCode());
    }

    public function testInternalApiRoutesDoNotRequireCsrfTokens(): void
    {
        $application = $this->bootApplication();
        $request = $this->signedInternalRequest($application, 'POST', '/internal/v1/session/introspect', [
            'session_token_hash' => hash('sha256', 'session-token'),
            'requested_scopes' => ['user:read'],
        ]);

        $response = $application->handle($request);

        self::assertSame(200, $response->statusCode());
    }

    public function testInternalApiLogsDoNotIncludeSecrets(): void
    {
        $application = $this->bootApplication();
        $headers = $this->signedInternalHeaders($application, 'GET', '/internal/v1/health');
        $signature = $headers['X-MF-Signature'];
        $request = new Request(
            'GET',
            '/internal/v1/health',
            headers: ['Accept' => 'application/json'],
            serverParameters: ['SCRIPT_NAME' => '/index.php'],
        );

        $application->handle($request);
        $logs = $this->logContents();

        self::assertStringNotContainsString($signature, $logs);
        self::assertStringNotContainsString($application->appConfig()->internalApiSecret, $logs);
    }
}
