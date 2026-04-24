<?php
declare(strict_types=1);

namespace Tests;

use MyFrancis\Core\Request;
use MyFrancis\Core\Response;
use MyFrancis\Security\CsrfTokenManager;

final class CsrfMiddlewareTest extends FrameworkTestCase
{
    public function testMissingTokenRejectsUnsafeWebMethod(): void
    {
        $application = $this->bootApplication();
        $this->registerCsrfProtectedRoutes($application);

        $response = $application->handle(new Request('POST', '/forms/submit', inputParameters: ['message' => 'hello']));

        self::assertSame(403, $response->statusCode());
    }

    public function testInvalidTokenRejectsUnsafeWebMethod(): void
    {
        $application = $this->bootApplication();
        $this->registerCsrfProtectedRoutes($application);

        $response = $application->handle(new Request('POST', '/forms/submit', inputParameters: [
            '_token' => 'invalid-token',
            'message' => 'hello',
        ]));

        self::assertSame(403, $response->statusCode());
    }

    public function testValidTokenAcceptsUnsafeWebMethod(): void
    {
        $application = $this->bootApplication();
        $this->registerCsrfProtectedRoutes($application);
        $token = $this->service(CsrfTokenManager::class)->generateToken();

        $response = $application->handle(new Request('POST', '/forms/submit', inputParameters: [
            '_token' => $token,
            'message' => 'hello',
        ]));

        self::assertSame(200, $response->statusCode());
        self::assertSame('submitted', $response->body());
    }

    public function testSafeGetDoesNotRequireCsrfToken(): void
    {
        $application = $this->bootApplication();
        $this->registerCsrfProtectedRoutes($application);

        $response = $application->handle(new Request('GET', '/forms/preview'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('preview', $response->body());
    }

    public function testCsrfDoesNotApplyToInternalApiRoutes(): void
    {
        $application = $this->bootApplication();

        $request = $this->signedInternalRequest($application, 'POST', '/internal/v1/session/introspect', [
            'session_token_hash' => hash('sha256', 'session-token'),
            'requested_scopes' => ['user:read'],
        ]);

        $response = $application->handle($request);

        self::assertSame(200, $response->statusCode());
    }

    public function testCsrfValidationUsesConstantTimeComparison(): void
    {
        $source = (string) file_get_contents($this->basePath . '/src/Security/CsrfTokenManager.php');

        self::assertStringContainsString('hash_equals', $source);
    }

    private function registerCsrfProtectedRoutes(\MyFrancis\Core\Application $application): void
    {
        $application->router()->post(
            '/forms/submit',
            static fn (): Response => Response::html('submitted'),
            middleware: ['request.id', 'security.headers', 'csrf'],
        );
        $application->router()->get(
            '/forms/preview',
            static fn (): Response => Response::html('preview'),
            middleware: ['request.id', 'security.headers', 'csrf'],
        );
    }
}
