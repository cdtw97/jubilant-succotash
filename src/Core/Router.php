<?php
declare(strict_types=1);

namespace MyFrancis\Core;

use Closure;
use MyFrancis\Core\Enums\HttpMethod;
use MyFrancis\Core\Exceptions\ConfigurationException;
use MyFrancis\Core\Exceptions\MethodNotAllowedHttpException;
use MyFrancis\Core\Exceptions\NotFoundHttpException;
use MyFrancis\Http\Middleware\MiddlewareInterface;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * @phpstan-type ControllerAction array{0: class-string, 1: non-empty-string}
 * @phpstan-type RouteAction Closure|ControllerAction
 * @phpstan-type RouteMiddleware Closure|string|MiddlewareInterface
 */
final class Router
{
    /** @var array<int, Route> */
    private array $routes = [];

    /** @var array<string, Route> */
    private array $namedRoutes = [];

    /** @var array<string, RouteMiddleware> */
    private array $middlewareAliases = [];

    /**
     * @param RouteAction $action
     * @param list<RouteMiddleware> $middleware
     * @param array<string, string> $constraints
     * @param array<string, mixed> $attributes
     */
    public function get(
        string $path,
        Closure|array $action,
        ?string $name = null,
        array $middleware = [],
        array $constraints = [],
        array $attributes = [],
    ): Route {
        return $this->add(HttpMethod::GET, $path, $action, $name, $middleware, $constraints, $attributes);
    }

    /**
     * @param RouteAction $action
     * @param list<RouteMiddleware> $middleware
     * @param array<string, string> $constraints
     * @param array<string, mixed> $attributes
     */
    public function post(
        string $path,
        Closure|array $action,
        ?string $name = null,
        array $middleware = [],
        array $constraints = [],
        array $attributes = [],
    ): Route {
        return $this->add(HttpMethod::POST, $path, $action, $name, $middleware, $constraints, $attributes);
    }

    /**
     * @param RouteAction $action
     * @param list<RouteMiddleware> $middleware
     * @param array<string, string> $constraints
     * @param array<string, mixed> $attributes
     */
    public function add(
        HttpMethod $method,
        string $path,
        Closure|array $action,
        ?string $name = null,
        array $middleware = [],
        array $constraints = [],
        array $attributes = [],
    ): Route {
        $route = new Route(
            $method,
            $path,
            $action,
            $name,
            $this->normalizeMiddleware($middleware),
            $constraints,
            $attributes,
        );
        $this->routes[] = $route;

        if ($name !== null) {
            $this->namedRoutes[$name] = $route;
        }

        return $route;
    }

    public function aliasMiddleware(string $name, Closure|string|MiddlewareInterface $middleware): void
    {
        $this->middlewareAliases[$name] = $middleware;
    }

    /**
     * @param array<string, bool|float|int|string|\Stringable|null> $parameters
     */
    public function url(string $name, array $parameters = []): string
    {
        if (! array_key_exists($name, $this->namedRoutes)) {
            throw new ConfigurationException(sprintf('Route [%s] is not defined.', $name));
        }

        return $this->namedRoutes[$name]->uri($parameters);
    }

    public function dispatch(Request $request, Container $container): Response
    {
        /** @var list<string> $allowedMethods */
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            $parameters = $route->match($request->path());

            if ($parameters === null) {
                continue;
            }

            if ($route->method->value !== $request->method()) {
                $allowedMethods[] = $route->method->value;
                continue;
            }

            $container->set(Route::class, $route);
            $container->set(Request::class, $request);

            $destination = function (Request $currentRequest) use ($route, $parameters, $container): Response {
                return $this->invokeAction($route->action, $parameters, $container, $currentRequest);
            };

            return $this->runMiddleware($route->middleware, $request, $destination, $container);
        }

        if ($allowedMethods !== []) {
            throw new MethodNotAllowedHttpException($allowedMethods);
        }

        throw new NotFoundHttpException();
    }

    /**
     * @param list<RouteMiddleware> $middlewareStack
     * @param Closure(Request): Response $destination
     */
    private function runMiddleware(
        array $middlewareStack,
        Request $request,
        Closure $destination,
        Container $container,
    ): Response {
        $pipeline = $destination;

        foreach (array_reverse($middlewareStack) as $middleware) {
            $next = $pipeline;
            $resolvedMiddleware = $this->resolveMiddleware($middleware, $container);

            if ($resolvedMiddleware instanceof MiddlewareInterface) {
                $pipeline = static function (Request $currentRequest) use ($resolvedMiddleware, $next, $container): Response {
                    $container->set(Request::class, $currentRequest);

                    return $resolvedMiddleware->process($currentRequest, $next);
                };
                continue;
            }

            $pipeline = static function (Request $currentRequest) use ($resolvedMiddleware, $next, $container): Response {
                $container->set(Request::class, $currentRequest);
                $result = $resolvedMiddleware($currentRequest, $next);

                if (! $result instanceof Response) {
                    throw new ConfigurationException('Route middleware must return a Response instance.');
                }

                return $result;
            };
        }

        return $pipeline($request);
    }

    /**
     * @param RouteAction $action
     * @param array<string, string> $routeParameters
     */
    private function invokeAction(
        Closure|array $action,
        array $routeParameters,
        Container $container,
        Request $request,
    ): Response {
        $container->set(Request::class, $request);

        if ($action instanceof Closure) {
            $result = $this->invokeReflection(new ReflectionFunction($action), $action, $routeParameters, $container);

            return $this->normalizeActionResult($result);
        }

        if (! class_exists($action[0])) {
            throw new ConfigurationException(sprintf('Controller class [%s] is not defined.', $action[0]));
        }

        $controller = $this->resolveObject($container, $action[0]);
        $reflectionMethod = new ReflectionMethod($controller, $action[1]);

        if (! $reflectionMethod->isPublic()) {
            throw new ConfigurationException('Route actions must point to public controller methods.');
        }

        if ($reflectionMethod->getDeclaringClass()->getName() !== $action[0]) {
            throw new ConfigurationException('Inherited controller methods cannot be exposed as routes.');
        }

        $result = $this->invokeReflection($reflectionMethod, $controller, $routeParameters, $container);

        return $this->normalizeActionResult($result);
    }

    /**
     * @param array<string, string> $routeParameters
     */
    private function invokeReflection(
        ReflectionFunctionAbstract $reflection,
        mixed $target,
        array $routeParameters,
        Container $container,
    ): mixed {
        /** @var list<mixed> $arguments */
        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $arguments[] = $container->get($type->getName());
                continue;
            }

            if (array_key_exists($parameter->getName(), $routeParameters)) {
                $arguments[] = $this->castRouteParameter($routeParameters[$parameter->getName()], $type);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new ConfigurationException(sprintf(
                'Unable to resolve action parameter [%s].',
                $parameter->getName(),
            ));
        }

        if ($reflection instanceof ReflectionFunction && $target instanceof Closure) {
            return $target(...$arguments);
        }

        if ($reflection instanceof ReflectionMethod && is_object($target)) {
            return $reflection->invokeArgs($target, $arguments);
        }

        throw new ConfigurationException('Unsupported route action.');
    }

    private function normalizeActionResult(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        throw new ConfigurationException('Route actions must return a Response instance or HTML string.');
    }

    private function castRouteParameter(string $value, mixed $type): bool|float|int|string
    {
        if (! $type instanceof ReflectionNamedType || ! $type->isBuiltin()) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'string' => $value,
            default => $value,
        };
    }

    /**
     * @param list<RouteMiddleware> $middleware
     * @return list<RouteMiddleware>
     */
    private function normalizeMiddleware(array $middleware): array
    {
        $normalized = [];

        foreach ($middleware as $entry) {
            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @param RouteMiddleware $middleware
     * @return Closure|MiddlewareInterface
     */
    private function resolveMiddleware(
        Closure|string|MiddlewareInterface $middleware,
        Container $container,
    ): Closure|MiddlewareInterface {
        if ($middleware instanceof Closure || $middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        $resolvedMiddleware = $this->middlewareAliases[$middleware] ?? $middleware;

        if ($resolvedMiddleware instanceof Closure || $resolvedMiddleware instanceof MiddlewareInterface) {
            return $resolvedMiddleware;
        }

        if (! class_exists($resolvedMiddleware) || ! $container->has($resolvedMiddleware)) {
            throw new ConfigurationException(sprintf('Middleware [%s] is not registered.', $middleware));
        }

        $service = $this->resolveObject($container, $resolvedMiddleware);

        if (! $service instanceof MiddlewareInterface) {
            throw new ConfigurationException(sprintf('Middleware [%s] is not registered.', $middleware));
        }

        return $service;
    }

    /**
     * @template TObject of object
     *
     * @param class-string<TObject> $className
     * @return TObject
     */
    private function resolveObject(Container $container, string $className): object
    {
        $service = $container->get($className);

        if (! $service instanceof $className) {
            throw new ConfigurationException(sprintf('Service [%s] could not be resolved.', $className));
        }

        return $service;
    }
}
