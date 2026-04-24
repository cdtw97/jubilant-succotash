<?php
declare(strict_types=1);

namespace Tests;

use MyFrancis\Core\Request;

final class SecurityHeadersMiddlewareTest extends FrameworkTestCase
{
    public function testNormalResponsesIncludeSecurityHeaders(): void
    {
        $application = $this->bootApplication();
        $response = $application->handle(new Request('GET', '/'));

        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options'] ?? null);
        self::assertSame('strict-origin-when-cross-origin', $response->headers()['Referrer-Policy'] ?? null);
        self::assertSame('DENY', $response->headers()['X-Frame-Options'] ?? null);
        self::assertStringContainsString("default-src 'self'", $response->headers()['Content-Security-Policy'] ?? '');
        self::assertArrayNotHasKey('Strict-Transport-Security', $response->headers());
    }

    public function testHstsIsEnabledOnlyForProductionHttpsResponses(): void
    {
        $application = $this->bootApplication(['APP_ENV' => 'production']);
        $response = $application->handle(new Request(
            'GET',
            '/',
            serverParameters: ['SCRIPT_NAME' => '/index.php', 'HTTPS' => 'on'],
        ));

        self::assertSame('max-age=31536000; includeSubDomains', $response->headers()['Strict-Transport-Security'] ?? null);
    }
}
