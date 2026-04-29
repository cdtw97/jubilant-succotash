<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use MyFrancis\Config\AppConfig;
use MyFrancis\Core\Container;
use MyFrancis\Core\Enums\HttpStatus;
use MyFrancis\Core\Request;
use MyFrancis\Core\Response;
use MyFrancis\Http\Middleware\MiddlewareInterface;
use MyFrancis\Security\SessionManager;

final class AuthenticateUserMiddleware implements MiddlewareInterface
{
    private const INTENDED_PATH_KEY = 'auth.intended_path';
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

        if (is_array($user) && $user !== []) {
            return $next(
                $request
                    ->withAttribute('auth.check', true)
                    ->withAttribute('auth.user', $user)
            );
        }

        if ($this->hasSessionCookie($request)) {
            /** @var SessionManager $sessionManager */
            $sessionManager = $this->container->get(SessionManager::class);
            $storedUserId = $sessionManager->get(self::SESSION_USER_ID);

            if (! $this->hasStoredUserId($storedUserId)) {
                return $this->unauthenticatedResponse($request);
            }

            /** @var AuthService $authService */
            $authService = $this->container->get(AuthService::class);
            $user = $authService->currentUser();

            if (is_array($user) && $user !== []) {
                return $next(
                    $request
                        ->withAttribute('auth.check', true)
                        ->withAttribute('auth.user', $user)
                );
            }
        }

        return $this->unauthenticatedResponse($request);
    }

    private function unauthenticatedResponse(Request $request): Response
    {
        if ($this->expectsJson($request)) {
            return Response::json([
                'error' => [
                    'code' => 'authentication_required',
                    'message' => 'Sign in to access this resource.',
                    'request_id' => $request->requestId(),
                ],
                'meta' => [
                    'login_url' => route('auth.login'),
                ],
            ], HttpStatus::UNAUTHORIZED);
        }

        /** @var SessionManager $sessionManager */
        $sessionManager = $this->container->get(SessionManager::class);
        $path = $request->path();

        if ($path !== '' && $path !== '/login' && $path !== '/register' && str_starts_with($path, '/')) {
            $sessionManager->put(self::INTENDED_PATH_KEY, $path);
        }

        return Response::redirect(route('auth.login'));
    }

    private function expectsJson(Request $request): bool
    {
        $accept = strtolower((string) $request->header('accept', ''));
        $contentType = strtolower((string) $request->header('content-type', ''));

        return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
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
