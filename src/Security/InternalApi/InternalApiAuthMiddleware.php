<?php
declare(strict_types=1);

namespace MyFrancis\Security\InternalApi;

use MyFrancis\Config\AppConfig;
use MyFrancis\Core\Container;
use MyFrancis\Core\Exceptions\ConfigurationException;
use MyFrancis\Core\Exceptions\ForbiddenHttpException;
use MyFrancis\Core\Request;
use MyFrancis\Core\Response;
use MyFrancis\Core\Route;
use MyFrancis\Http\Middleware\MiddlewareInterface;
use MyFrancis\Support\Logger;

final class InternalApiAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Container $container,
        private readonly AppConfig $appConfig,
        private readonly HmacVerifier $hmacVerifier,
        private readonly Logger $logger,
    ) {
    }

    /**
     * @param callable(Request): Response $next
     */
    #[\Override]
    public function process(Request $request, callable $next): Response
    {
        if ($this->appConfig->isProduction() && ! $request->isSecure()) {
            throw new ForbiddenHttpException('HTTPS is required for internal API requests in production.');
        }

        $route = $this->container->get(Route::class);
        $requiredScope = $route->attribute('scope');

        if (! $requiredScope instanceof InternalScope) {
            throw new ConfigurationException('Internal API routes must declare a valid scope.');
        }

        $context = $this->hmacVerifier->verify($request, $requiredScope);
        $this->container->set(InternalApiRequestContext::class, $context);

        $this->logger->info('Internal API request authenticated.', [
            'request_id' => $request->requestId(),
            'path' => $request->path(),
            'method' => $request->method(),
            'app_id' => $context->appId,
            'key_id' => $context->keyId,
            'scope' => $context->requiredScope->value,
        ]);

        return $next($request);
    }
}
