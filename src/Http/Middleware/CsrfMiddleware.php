<?php
declare(strict_types=1);

namespace MyFrancis\Http\Middleware;

use MyFrancis\Core\Exceptions\InvalidCsrfTokenException;
use MyFrancis\Core\Request;
use MyFrancis\Core\Response;
use MyFrancis\Security\CsrfTokenManager;
use MyFrancis\Support\Logger;

final class CsrfMiddleware implements MiddlewareInterface
{
    private const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private readonly CsrfTokenManager $csrfTokenManager,
        private readonly Logger $logger,
    ) {
    }

    /**
     * @param callable(Request): Response $next
     */
    #[\Override]
    public function process(Request $request, callable $next): Response
    {
        if (! in_array($request->method(), self::UNSAFE_METHODS, true)) {
            return $next($request);
        }

        $token = $request->input('_token');

        if (! is_string($token) || $token === '') {
            $headerToken = $request->header('x-csrf-token');
            $token = is_string($headerToken) ? $headerToken : null;
        }

        if (! $this->csrfTokenManager->validateToken($token)) {
            $this->logger->warning('Web request rejected due to invalid CSRF token.', [
                'request_id' => $request->requestId(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            throw new InvalidCsrfTokenException();
        }

        return $next($request);
    }
}
