<?php
declare(strict_types=1);

namespace Tests;

use MyFrancis\Core\Request;

final class SessionIntrospectionControllerTest extends FrameworkTestCase
{
    public function testSessionIntrospectionRequiresValidSignedRequest(): void
    {
        $application = $this->bootApplication();
        $request = new Request(
            'POST',
            '/internal/v1/session/introspect',
            headers: [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8',
            ],
            rawBody: '{"session_token_hash":"abc","requested_scopes":["user:read"]}',
            serverParameters: ['SCRIPT_NAME' => '/index.php'],
        );

        $response = $application->handle($request);

        self::assertSame(400, $response->statusCode());
    }

    public function testSessionIntrospectionRequiresScope(): void
    {
        $application = $this->bootApplication(['INTERNAL_API_SCOPES' => 'health:read']);
        $request = $this->signedInternalRequest($application, 'POST', '/internal/v1/session/introspect', [
            'session_token_hash' => hash('sha256', 'session-token'),
            'requested_scopes' => ['user:read'],
        ]);

        $response = $application->handle($request);

        self::assertSame(403, $response->statusCode());
    }

    public function testSessionIntrospectionRequiresSessionTokenHash(): void
    {
        $application = $this->bootApplication();
        $request = $this->signedInternalRequest($application, 'POST', '/internal/v1/session/introspect', [
            'requested_scopes' => ['user:read'],
        ]);

        $response = $application->handle($request);

        self::assertSame(422, $response->statusCode());
    }

    public function testSessionIntrospectionRequiresRequestedScopesArray(): void
    {
        $application = $this->bootApplication();
        $request = $this->signedInternalRequest($application, 'POST', '/internal/v1/session/introspect', [
            'session_token_hash' => hash('sha256', 'session-token'),
            'requested_scopes' => 'user:read',
        ]);

        $response = $application->handle($request);

        self::assertSame(422, $response->statusCode());
    }

    public function testSessionIntrospectionReturnsSanitizedEnvelope(): void
    {
        $application = $this->bootApplication();
        $request = $this->signedInternalRequest($application, 'POST', '/internal/v1/session/introspect', [
            'session_token_hash' => hash('sha256', 'session-token'),
            'requested_scopes' => ['user:read', 'invoice:read'],
        ]);

        $response = $application->handle($request);

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('"data"', $response->body());
        self::assertStringContainsString('"meta"', $response->body());
        self::assertStringContainsString('"request_id"', $response->body());
        self::assertStringContainsString('"invoice:read"', $response->body());
    }
}
