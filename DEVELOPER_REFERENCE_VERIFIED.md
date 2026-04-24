# Developer Reference

## Middleware Directory

Core middleware aliases are registered in `MyFrancis\Core\Application::registerMiddlewareAliases()`.

| Alias | Class | Typical lane | What it enforces | Common failure mode |
|---|---|---|---|---|
| `request.id` | `MyFrancis\Http\Middleware\RequestIdMiddleware` | Web and Internal | On web routes, accepts a valid `X-Request-Id` or generates one. On internal routes, requires a valid `X-MF-Request-Id`. Adds `X-Request-Id` to the response. | Internal routes return `400 Bad Request` if `X-MF-Request-Id` is missing or invalid. |
| `security.headers` | `MyFrancis\Security\SecurityHeadersMiddleware` | Web and Internal | Adds `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: DENY`, and a restrictive `Content-Security-Policy`. Adds `Strict-Transport-Security` only in production over HTTPS. | No direct failure path; it decorates the response. |
| `csrf` | `MyFrancis\Http\Middleware\CsrfMiddleware` | Web | Enforces a valid CSRF token for `POST`, `PUT`, `PATCH`, and `DELETE`. Reads `_token` from input first, then `X-CSRF-Token`. Uses `CsrfTokenManager`. | Invalid or missing tokens produce `403 Forbidden` through `InvalidCsrfTokenException`. |
| `json.body` | `MyFrancis\Http\Middleware\JsonBodyMiddleware` | Internal | Rejects browser cookies, requires `Accept: application/json`, requires `Content-Type: application/json; charset=utf-8`, validates JSON syntax, and requires the decoded payload to be an object rather than a top-level list. | Cookies or invalid JSON produce `400`; missing `Accept` produces `406`; invalid or missing `Content-Type` produces `415`. |
| `internal.auth` | `MyFrancis\Security\InternalApi\InternalApiAuthMiddleware` | Internal | In production, requires HTTPS. Reads the route’s `scope` attribute, verifies HMAC credentials, timestamp freshness, nonce uniqueness, signature validity, and route authorization, then stores `InternalApiRequestContext` in the container. Routes using this middleware must declare a valid `InternalScope` in route attributes. | Invalid credentials, signatures, timestamps, replayed nonces, or missing headers produce `401`; missing scope authorization or insecure production transport produce `403`. A missing or invalid route `scope` declaration is a configuration error. |

### Middleware contract

Class-based middleware implements the exact interface below:

```php
<?php
declare(strict_types=1);

namespace MyFrancis\Http\Middleware;

use MyFrancis\Core\Request;
use MyFrancis\Core\Response;

interface MiddlewareInterface
{
    /**
     * @param callable(Request): Response $next
     */
    public function process(Request $request, callable $next): Response;
}
```

### Closure middleware

Routes may also attach closures. This exact pattern is used in the test suite:

```php
$router->get(
    '/middleware-check',
    static fn (): Response => Response::html('ok'),
    middleware: [
        /**
         * @param callable(Request): Response $next
         */
        static function (Request $request, callable $next): Response {
            return $next($request);
        },
    ],
);
```

### Registering a custom alias

Use the router directly:

```php
$application->router()->aliasMiddleware('tenant', TenantMiddleware::class);
```

Then use the alias in any route:

```php
middleware: ['request.id', 'tenant', 'security.headers']
```

---

## Controller and Response Helpers

### Base controller helpers

`MyFrancis\Core\Controller` exposes two protected helpers:

- `view(string $view, array $data = [], HttpStatus|int $status = HttpStatus::OK, array $headers = []): Response`
- `json(mixed $data, Request $request, HttpStatus|int $status = HttpStatus::OK, array $headers = []): JsonResponse`

In source form, the JSON helper is:

```php
protected function json(
    mixed $data,
    Request $request,
    HttpStatus|int $status = HttpStatus::OK,
    array $headers = [],
): JsonResponse
```

It delegates to `JsonResponse::success(...)` using `Request::requestId()`.

### Response helpers

The core response helpers are:

- `Response::html(string $html, HttpStatus|int $status = HttpStatus::OK, array $headers = []): self`
- `Response::json(array $payload, HttpStatus|int $status = HttpStatus::OK, array $headers = []): self`
- `Response::redirect(string $location, HttpStatus|int $status = HttpStatus::FOUND, array $headers = []): self`

`JsonResponse` adds the internal JSON envelope:

- `JsonResponse::success(mixed $data, string $requestId, string $apiVersion = 'v1', HttpStatus|int $status = HttpStatus::OK, array $headers = []): self`
- `JsonResponse::error(string $code, string $message, string $requestId, HttpStatus|int $status = HttpStatus::BAD_REQUEST, array $headers = []): self`

The difference is important:

- `Response::json(...)` sends exactly the array payload you provide
- `JsonResponse::success(...)` wraps your data in the framework’s `data` / `meta` envelope
- `JsonResponse::error(...)` wraps failures in the framework’s `error` envelope

---

## View System

`MyFrancis\Core\View` is a plain PHP renderer.

### Rendering contract

The exact method is:

```php
public function render(string $view, array $data = []): string
```

### Dot notation

View names use dot notation and map directly to files under `resources/views`.

Examples:

- `pages.index` -> `resources/views/pages/index.php`
- `pages.about` -> `resources/views/pages/about.php`
- `hello.show` -> `resources/views/hello/show.php`

### Data injection with `extract()`

`View::render(...)` prepares a few variables, then executes:

```php
extract($data, EXTR_SKIP);
```

That means:

- keys in `$data` become local variables inside the view
- existing variables are preserved because `EXTR_SKIP` is used
- the renderer’s own `$app` and `$escaper` variables remain available

So views can rely on:

- passed data such as `$title`, `$text`, or `$message`
- `$app` as the current `AppConfig`
- `$escaper` as the current `Escaper`

### Layout composition

There is no template DSL and no layout engine. Layouts are ordinary PHP includes.

The built-in views use this pattern:

```php
require dirname(__DIR__) . '/layouts/header.php';
```

then page content, then:

```php
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
```

### View safety rules

`View::resolveViewPath(...)` rejects unsafe view names. It will throw `ViewException` for names containing:

- `..`
- a leading `/`
- backslashes
- null bytes

It also verifies that the resolved `realpath(...)` stays inside the configured view base path.

---

## Global Helpers

The bootstrap file defines the global helper functions listed below.

| Helper | Purpose | Notes |
|---|---|---|
| `e(mixed $value): string` | HTML-escapes output | Uses `MyFrancis\Security\Escaper::html(...)`. |
| `route(string $name, array $parameters = []): string` | Generates a URL from a named route | Delegates to `Application::getInstance()->router()->url(...)`, then prefixes the runtime base path. If there is no active application instance, it falls back to `app_url()`. If the named route does not exist, `Router::url(...)` throws `ConfigurationException`. |
| `asset(string $path = ''): string` | Generates a URL to a public asset | Uses the current script directory via `base_path_url()` / `app_url()` rather than `APP_URL`. |
| `csrf_token(): string` | Returns the session-backed CSRF token | Uses `CsrfTokenManager::generateToken()`, which starts session-backed token storage lazily when needed. |

### Companion helper: `csrf_field()`

`csrf_field()` is also defined by the bootstrap and is the fastest way to add the hidden input expected by `CsrfMiddleware`:

```php
<?= csrf_field() ?>
```

### Typical usage

```php
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<a href="<?= e(route('pages.about')) ?>">About</a>
<input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
```

---

## Rules of the House

### Keep controllers thin

Controllers are expected to orchestrate, not own the application.

A controller should usually:

- read input from `Request`
- validate request shape at the edge
- call services or repositories
- return a view or explicit JSON response

This convention is reinforced by the framework itself:

- there is no model base class doing hidden work
- there is no magic request-to-model binding
- routes stay explicit
- controller actions stay small and readable

### JSON is explicit

Route actions may return:

- a `Response`
- an HTML `string`

They may **not** return an array and expect auto-JSON conversion.

For JSON, be explicit:

```php
return JsonResponse::success([...], $request->requestId());
```

or:

```php
return Response::json([...]);
```

or, inside a controller:

```php
return $this->json([...], $request);
```

### JSON parsing is middleware-driven

`Request::capture()` does not parse JSON into `Request::json()` on its own.

For live HTTP requests, `Request::json()` becomes useful only when:

- `json.body` runs and calls `withJsonPayload(...)`, or
- you manually construct a `Request` with the `jsonPayload` constructor argument

Without that, the raw request body still exists, but it is available through `Request::rawBody()`.

### The model layer is intentionally absent

The framework does not ship with:

- an ORM
- Active Record models
- migrations
- a query builder DSL
- implicit database conventions

Instead, it gives you:

- `MyFrancis\Database\Database`
- `MyFrancis\Database\Repository`

The expectation is that your application creates explicit repositories and services that fit the domain you are building.

### Internal controllers still inherit the base controller contract

Even if a controller only returns JSON, extending `MyFrancis\Core\Controller` means the base `View` dependency still exists.

That is why source controllers such as `SessionIntrospectionController` still call:

```php
public function __construct(View $view)
{
    parent::__construct($view);
}
```

If you do not need extra constructor dependencies, you may also omit the constructor and let the inherited one be autowired.

### Route exposure is explicit

A public method on a base controller is not automatically routable through a child controller.

The router checks `ReflectionMethod::getDeclaringClass()` and rejects inherited action methods. That keeps route exposure intentional and auditable.

---

## Troubleshooting

### My controller returned an array and the route failed

This is expected. `Router::normalizeActionResult(...)` accepts only:

- `Response`
- `string`

Anything else triggers:

```text
ConfigurationException: Route actions must return a Response instance or HTML string.
```

Use one of these instead:

```php
return JsonResponse::success([...], $request->requestId());
```

```php
return Response::json([...]);
```

```php
return Response::html('<p>Hello</p>');
```

### `Request::json()` is empty in my controller

That usually means the route does not use `json.body`.

`Request::capture()` stores the raw body, but it does not parse JSON into the `jsonPayload` property. Add the middleware:

```php
middleware: ['request.id', 'json.body', 'internal.auth', 'security.headers']
```

or parse `Request::rawBody()` yourself.

### I tried to inject an interface and autowiring failed

That is also expected. `Container::get(...)` only auto-makes concrete classes that pass `class_exists(...)`. An unbound interface produces:

```text
ContainerException: Service [App\Contracts\PaymentGateway] is not defined.
```

Bind the interface explicitly under the interface ID:

```php
use App\Contracts\PaymentGateway;
use App\Services\StripePaymentGateway;
use MyFrancis\Core\Container;
use MyFrancis\Support\Logger;

$container->factory(PaymentGateway::class, function (Container $container): PaymentGateway {
    return new StripePaymentGateway($container->get(Logger::class));
});
```

### I tried to inject a scalar constructor argument and autowiring failed

`Container::make(...)` can resolve non-builtin class types and constructor defaults. It cannot invent scalar values.

The failure looks like:

```text
ContainerException: Unable to resolve constructor parameter [apiKey] for [App\Services\WebhookPublisher].
```

Register a factory for that class and supply the scalar yourself.

### My action parameter could not be resolved

Builtin action parameters come from route placeholders by **name**.

If your method is:

```php
public function show(int $id): Response
```

then the route must expose `{id}`:

```php
$router->get('/users/{id}', [UserController::class, 'show']);
```

Otherwise `Router::invokeReflection(...)` throws:

```text
ConfigurationException: Unable to resolve action parameter [id].
```

### I sent `X-Request-Id` to an internal route and got rejected

Internal request ID input uses **`X-MF-Request-Id`**, not `X-Request-Id`.

The naming split is intentional:

- internal clients **send** `X-MF-Request-Id`
- the framework **responds** with `X-Request-Id`

### My internal endpoint returns `406`, `415`, or `400`

If the route uses `json.body`, it enforces all of the following:

- `Accept: application/json`
- `Content-Type: application/json; charset=utf-8`
- no browser cookies
- valid JSON syntax
- a top-level JSON object

So the common mappings are:

- `406 Not Acceptable` -> missing or incompatible `Accept`
- `415 Unsupported Media Type` -> missing or invalid `Content-Type`
- `400 Bad Request` -> cookies present, invalid JSON, or a top-level JSON list

Because the middleware checks headers before body emptiness, even `GET` routes that use `json.body` still need the JSON headers.


### I added `internal.auth`, but the route now fails before my controller runs

Routes using `internal.auth` must declare a valid `InternalScope` in the route attributes:

```php
attributes: [
    'scope' => InternalScope::HEALTH_READ,
]
```

If `scope` is missing or not an `InternalScope` enum case, `InternalApiAuthMiddleware` throws:

```text
ConfigurationException: Internal API routes must declare a valid scope.
```

That is a route configuration problem, not a credential problem.

### I set a route attribute, but I cannot read it from `Request`

Route attributes live on `MyFrancis\Core\Route`, not on `Request`.

This works inside middleware or any class that has the container:

```php
use MyFrancis\Core\Container;
use MyFrancis\Core\Route;

$route = $container->get(Route::class);
$scope = $route->attribute('scope');
```

If you want request attributes, add middleware that calls `Request::withAttribute(...)`.

A related nuance: the built-in internal routes include `expects_json`, but the core does not copy that value onto the request automatically.

### `route()` and `asset()` are not using `APP_URL`

That is the current design.

Both helpers derive their prefix from the executing script directory through `SCRIPT_NAME`, which makes subdirectory deployments work cleanly. `APP_URL` exists in `AppConfig`, but it is not the source of generated asset and route URLs.

### My internal controller only returns JSON. Why does it still need `View $view`?

Because it extends `MyFrancis\Core\Controller`, and the base constructor requires `View`.

You can either:

- keep `View $view` and call `parent::__construct($view)`, or
- omit the constructor entirely and let the inherited constructor be autowired

### I pointed a route at an inherited public method and it was rejected

This is expected. The router allows only methods declared on the target controller class itself.

Move the action onto the concrete controller and route to that method explicitly.

### Internal auth works locally, but fails in production over HTTP

`InternalApiAuthMiddleware` enforces HTTPS when `AppConfig::isProduction()` is true.

In production, internal routes must be served over HTTPS or the middleware returns `403 Forbidden`.