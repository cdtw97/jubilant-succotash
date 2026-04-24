<?php
declare(strict_types=1);

namespace Tests;

use MyFrancis\Security\SessionManager;

final class SessionSecurityTest extends FrameworkTestCase
{
    public function testSessionCookieParametersAreHardenedAndSessionNameIsConfigured(): void
    {
        $this->bootApplication();
        $sessionManager = $this->service(SessionManager::class);
        $sessionManager->start();

        $cookieParams = session_get_cookie_params();

        self::assertSame('1', ini_get('session.use_only_cookies'));
        self::assertSame('1', ini_get('session.use_strict_mode'));
        self::assertSame('1', ini_get('session.cookie_httponly'));
        self::assertSame('0', ini_get('session.use_trans_sid'));
        self::assertTrue($cookieParams['httponly']);
        self::assertSame('Lax', $cookieParams['samesite']);
        self::assertSame('mf_session', session_name());
    }

    public function testSessionSecureFlagFollowsConfiguration(): void
    {
        $this->bootApplication(['SESSION_SECURE' => 'true']);
        $sessionManager = $this->service(SessionManager::class);
        $sessionManager->start();

        $cookieParams = session_get_cookie_params();

        self::assertTrue($cookieParams['secure']);
    }

    public function testRegenerateIdCanBeCalled(): void
    {
        $this->bootApplication();
        $sessionManager = $this->service(SessionManager::class);
        $sessionManager->start();
        $originalId = session_id();

        $sessionManager->regenerateId(false);
        $newId = session_id();

        self::assertNotSame('', $newId);
        self::assertNotSame($originalId, $newId);
    }

    public function testInternalApiRoutesDoNotStartBrowserSessions(): void
    {
        $application = $this->bootApplication();
        $request = $this->signedInternalRequest($application, 'GET', '/internal/v1/health');

        $response = $application->handle($request);

        self::assertSame(200, $response->statusCode());
        self::assertSame(PHP_SESSION_NONE, session_status());
    }
}
