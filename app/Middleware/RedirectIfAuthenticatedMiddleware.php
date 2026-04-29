<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use MyFrancis\Config\AppConfig;
use MyFrancis\Core\Container;
use MyFrancis\Core\Request;
use MyFrancis\Core\Response;
use MyFrancis\Http\Middleware\MiddlewareInterface;
use MyFrancis\Security\SessionManager;

final class RedirectIfAuthenticatedMiddleware implements MiddlewareInterface
{
    private const SESSION_USER_ID = 'auth.user_id';

    public function __construct(
        private readonly AppConfig $appConfig,
        private readonly Container $container,
    ) {
    }

    /**
     * @param callable(Request): Response $next
     */
    #[\Override]
    public function process(Request $request, callable $next): Response
    {
        $user = $request->attribute('auth.user');

        if (! is_array($user) && $this->hasSessionCookie($request)) {
            /** @var SessionManager $sessionManager */
            $sessionManager = $this->container->get(SessionManager::class);
            $storedUserId = $sessionManager->get(self::SESSION_USER_ID);

            if (! $this->hasStoredUserId($storedUserId)) {
                return $next(
                    $request
                        ->withAttribute('auth.check', false)
                        ->withAttribute('auth.user', null)
                );
            }

            /** @var AuthService $authService */
            $authService = $this->container->get(AuthService::class);
            $user = $authService->currentUser();
        }

        if (is_array($user) && $user !== []) {
            return Response::redirect(route('user.profile'));
        }

        return $next(
            $request
                ->withAttribute('auth.check', false)
                ->withAttribute('auth.user', null)
        );
    }

    private function hasSessionCookie(Request $request): bool
    {
        $cookieValue = $request->cookie($this->appConfig->sessionName);

        return is_string($cookieValue) && $cookieValue !== '';
    }

    private function hasStoredUserId(mixed $userId): bool
    {
        return (is_int($userId) && $userId > 0)
            || (is_string($userId) && ctype_digit($userId) && (int) $userId > 0);
    }
}
