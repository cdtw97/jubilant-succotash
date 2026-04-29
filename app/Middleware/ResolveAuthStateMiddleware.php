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

final class ResolveAuthStateMiddleware implements MiddlewareInterface
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
        if (! $this->hasSessionCookie($request)) {
            return $next(
                $request
                    ->withAttribute('auth.check', false)
                    ->withAttribute('auth.user', null)
            );
        }

        /** @var SessionManager $sessionManager */
        $sessionManager = $this->container->get(SessionManager::class);
        $userId = $sessionManager->get(self::SESSION_USER_ID);

        if (! $this->hasStoredUserId($userId)) {
            return $next(
                $request
                    ->withAttribute('auth.check', false)
                    ->withAttribute('auth.user', null)
            );
        }

        /** @var AuthService $authService */
        $authService = $this->container->get(AuthService::class);
        $user = $authService->currentUser();

        return $next(
            $request
                ->withAttribute('auth.check', $user !== null)
                ->withAttribute('auth.user', $user)
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
