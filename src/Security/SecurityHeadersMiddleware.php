<?php
declare(strict_types=1);

namespace MyFrancis\Security;

use MyFrancis\Config\AppConfig;
use MyFrancis\Core\Request;
use MyFrancis\Core\Response;
use MyFrancis\Http\Middleware\MiddlewareInterface;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AppConfig $appConfig)
    {
    }

    /**
     * @param callable(Request): Response $next
     */
    #[\Override]
    public function process(Request $request, callable $next): Response
    {
        $response = $next($request)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Content-Security-Policy', "default-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; object-src 'none'");

        if ($this->appConfig->isProduction() && $request->isSecure()) {
            $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
