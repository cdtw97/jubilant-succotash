<?php
declare(strict_types=1);

namespace MyFrancis\Core;

use MyFrancis\Config\AppConfig;
use MyFrancis\Config\DatabaseConfig;
use MyFrancis\Core\Exceptions\ConfigurationException;
use MyFrancis\Database\Database;
use MyFrancis\Http\Middleware\CsrfMiddleware;
use MyFrancis\Http\Middleware\JsonBodyMiddleware;
use MyFrancis\Http\Middleware\RequestIdMiddleware;
use MyFrancis\Security\CsrfTokenManager;
use MyFrancis\Security\Escaper;
use MyFrancis\Security\InternalApi\HmacSigner;
use MyFrancis\Security\InternalApi\HmacVerifier;
use MyFrancis\Security\InternalApi\InternalApiAuthMiddleware;
use MyFrancis\Security\InternalApi\NonceStore;
use MyFrancis\Security\SecurityHeadersMiddleware;
use MyFrancis\Security\SessionManager;
use MyFrancis\Support\Logger;
use Throwable;

final class Application
{
    private const API_VERSION = 'v1';

    private static ?self $instance = null;

    private readonly Router $router;

    public function __construct(
        private readonly string $basePath,
        private readonly Container $container,
        private readonly AppConfig $appConfig,
        private readonly DatabaseConfig $databaseConfig,
    ) {
        $this->router = new Router();
        self::$instance = $this;

        $this->registerCoreServices();
        $this->registerMiddlewareAliases();
        $this->container->get(ErrorHandler::class)->register();
    }

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    public function basePath(string $path = ''): string
    {
        if ($path === '') {
            return $this->basePath;
        }

        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));

        return $this->basePath . DIRECTORY_SEPARATOR . $normalizedPath;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function appConfig(): AppConfig
    {
        return $this->appConfig;
    }

    public function loadRoutes(string $routeFile): self
    {
        if (! is_file($routeFile)) {
            throw new ConfigurationException(sprintf('Route file [%s] was not found.', $routeFile));
        }

        $loader = require $routeFile;

        if (! is_callable($loader)) {
            throw new ConfigurationException(sprintf('Route file [%s] must return a callable.', $routeFile));
        }

        $loader($this->router);

        return $this;
    }

    public function handle(Request $request): Response
    {
        $this->container->set(Request::class, $request);

        try {
            $response = $this->router->dispatch($request, $this->container);
        } catch (Throwable $exception) {
            /** @var Request $currentRequest */
            $currentRequest = $this->container->get(Request::class);
            $response = $this->container->get(ErrorHandler::class)->handle($currentRequest, $exception);
        }

        /** @var Request $finalRequest */
        $finalRequest = $this->container->get(Request::class);

        return $this->finalizeResponse($finalRequest, $response);
    }

    private function registerCoreServices(): void
    {
        $this->container->set(self::class, $this);
        $this->container->set(Container::class, $this->container);
        $this->container->set(Router::class, $this->router);
        $this->container->set(AppConfig::class, $this->appConfig);
        $this->container->set(DatabaseConfig::class, $this->databaseConfig);

        $this->container->factory(Escaper::class, static fn (Container $container): Escaper => new Escaper());
        $this->container->factory(Logger::class, function (Container $container): Logger {
            return new Logger($this->basePath('storage/logs/app.log'));
        });
        $this->container->factory(ErrorHandler::class, function (Container $container): ErrorHandler {
            return new ErrorHandler(
                $this->appConfig,
                $container->get(Escaper::class),
                $container->get(Logger::class),
            );
        });
        $this->container->factory(View::class, function (Container $container): View {
            return new View(
                $this->basePath('resources/views'),
                $container->get(Escaper::class),
                $this->appConfig,
            );
        });
        $this->container->factory(Database::class, function (Container $container): Database {
            return new Database($this->databaseConfig);
        });
        $this->container->factory(SessionManager::class, function (Container $container): SessionManager {
            return new SessionManager(
                $this->appConfig,
                $this->basePath('storage/framework/sessions/' . $this->appConfig->sessionName),
            );
        });
        $this->container->factory(CsrfTokenManager::class, function (Container $container): CsrfTokenManager {
            return new CsrfTokenManager(
                $container->get(SessionManager::class),
                $container->get(Escaper::class),
            );
        });
        $this->container->factory(NonceStore::class, function (Container $container): NonceStore {
            return new NonceStore($this->basePath('storage/framework/nonces'));
        });
        $this->container->factory(HmacSigner::class, function (Container $container): HmacSigner {
            return new HmacSigner();
        });
        $this->container->factory(HmacVerifier::class, function (Container $container): HmacVerifier {
            return new HmacVerifier(
                $this->appConfig,
                $container->get(HmacSigner::class),
                $container->get(NonceStore::class),
            );
        });
        $this->container->factory(RequestIdMiddleware::class, function (Container $container): RequestIdMiddleware {
            return new RequestIdMiddleware($container->get(Logger::class));
        });
        $this->container->factory(SecurityHeadersMiddleware::class, function (Container $container): SecurityHeadersMiddleware {
            return new SecurityHeadersMiddleware($this->appConfig);
        });
        $this->container->factory(CsrfMiddleware::class, function (Container $container): CsrfMiddleware {
            return new CsrfMiddleware(
                $container->get(CsrfTokenManager::class),
                $container->get(Logger::class),
            );
        });
        $this->container->factory(JsonBodyMiddleware::class, function (Container $container): JsonBodyMiddleware {
            return new JsonBodyMiddleware($container->get(Logger::class));
        });
        $this->container->factory(InternalApiAuthMiddleware::class, function (Container $container): InternalApiAuthMiddleware {
            return new InternalApiAuthMiddleware(
                $container,
                $this->appConfig,
                $container->get(HmacVerifier::class),
                $container->get(Logger::class),
            );
        });
    }

    private function registerMiddlewareAliases(): void
    {
        $this->router->aliasMiddleware('request.id', RequestIdMiddleware::class);
        $this->router->aliasMiddleware('security.headers', SecurityHeadersMiddleware::class);
        $this->router->aliasMiddleware('csrf', CsrfMiddleware::class);
        $this->router->aliasMiddleware('json.body', JsonBodyMiddleware::class);
        $this->router->aliasMiddleware('internal.auth', InternalApiAuthMiddleware::class);
    }

    private function finalizeResponse(Request $request, Response $response): Response
    {
        $response = $response->withHeader('X-Request-Id', $request->requestId());

        if (str_starts_with($request->path(), '/internal/')) {
            $response = $response
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('X-Internal-API-Version', self::API_VERSION);
        }

        return $response;
    }
}
