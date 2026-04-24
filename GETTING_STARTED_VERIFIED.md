# Getting Started

## The Framework as a Two-Lane Request Pipeline

This framework is best understood as a **Two-Lane Request Pipeline**.

- The **Web lane** serves browser-facing HTML pages and form flows.
- The **Internal lane** serves signed JSON endpoints under **`/internal/v1/*`**.

Both lanes use the same core runtime:

- `public/index.php` as the front controller
- `app/bootstrap.php` as the composition root
- `MyFrancis\Core\Application` as the application kernel
- `MyFrancis\Core\Router` as the explicit dispatcher
- per-route middleware stacks
- controller actions that return `Response` objects or HTML strings

What changes between lanes is the **route definition** and the **middleware stack** attached to it.

---

## Installation

### Requirements

- PHP **8.3+**
- Composer
- PHP extensions required by Composer:
  - `ext-json`
  - `ext-pdo`

For database work, note one implementation detail from the source: `MyFrancis\Config\DatabaseConfig::dsn()` builds a **MySQL** DSN. In practice that means you also need a PDO driver that can open MySQL connections.

### Install and run

```bash
composer install
cp .env.example .env
php -S localhost:8000 -t public
```

The default route files expose these endpoints immediately:

- `GET /`
- `GET /about`
- `GET /internal/v1/health`

---

## Bootstrapping and Startup Flow

### `public/index.php`

The front controller is intentionally small. Its job is to load dependencies, bootstrap the application, load the route files, capture the request, and send the response.

The runtime chain is the exact code from `public/index.php`:

```php
$application
    ->loadRoutes($basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php')
    ->loadRoutes($basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'internal.php')
    ->handle(Request::capture())
    ->send();
```

The important method signature is:

```php
Application::handle(Request $request): Response
```

### `app/bootstrap.php`

`app/bootstrap.php` is the composition root. It does four things:

1. loads `.env` and `.env.example` through `MyFrancis\Support\Env::load(...)`
2. defines the global helpers used by views and route generation
3. creates the `MyFrancis\Core\Container`
4. returns a fully constructed `MyFrancis\Core\Application`

The bootstrap returns the application like this:

```php
return new Application(
    $basePath,
    $container,
    AppConfig::fromEnv(),
    DatabaseConfig::fromEnv(),
);
```

During `Application::__construct(...)`, the framework also:

- creates the `Router`
- registers core services such as `View`, `Database`, `SessionManager`, `CsrfTokenManager`, `NonceStore`, `Logger`, and `ErrorHandler`
- registers middleware aliases:
  - `request.id`
  - `security.headers`
  - `csrf`
  - `json.body`
  - `internal.auth`
- registers centralized error handling

---

## Hello World (Web)

This is the smallest browser-facing page you can add.

### Step 1: Add a route in `routes/web.php`

Route files return a callable with the exact shape `static function (Router $router): void`.

```php
<?php
declare(strict_types=1);

use App\Controllers\HelloController;
use MyFrancis\Core\Router;

return static function (Router $router): void {
    $router->get(
        '/hello',
        [HelloController::class, 'show'],
        name: 'hello.show',
        middleware: ['request.id', 'security.headers', 'csrf'],
    );
};
```

About that middleware stack:

- `request.id` accepts a valid incoming `X-Request-Id` or generates one.
- `security.headers` adds CSP, clickjacking, referrer, and MIME-sniffing protections.
- `csrf` is harmless on `GET`; it becomes active for unsafe methods such as `POST`, `PUT`, `PATCH`, and `DELETE`.

### Step 2: Create a controller

Controllers extend `MyFrancis\Core\Controller`. A web action typically returns `MyFrancis\Core\Response`.

```php
<?php
declare(strict_types=1);

namespace App\Controllers;

use MyFrancis\Core\Controller;
use MyFrancis\Core\Response;

final class HelloController extends Controller
{
    public function show(): Response
    {
        return $this->view('hello.show', [
            'title' => 'Hello',
            'message' => 'Hello from the web lane.',
        ]);
    }
}
```

This works without a custom constructor because `HelloController` inherits the base controller constructor:

```php
public function __construct(protected readonly View $viewRenderer)
```

The container can autowire that inherited `View` dependency automatically.

### Step 3: Render a view

Create `resources/views/hello/show.php`:

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/layouts/header.php';
?>
<main class="container">
    <h1><?= e($title ?? '') ?></h1>
    <p><?= e($message ?? '') ?></p>
</main>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
```

This follows the same plain-PHP layout pattern used by the built-in page views.

### What happens on `GET /hello`

The request flow is:

1. `public/index.php` captures the request with `Request::capture()`
2. `Application::handle(...)` stores the `Request` in the container
3. `Router::dispatch(...)` matches `/hello`
4. middleware runs in declared order: `request.id`, `security.headers`, `csrf`
5. `HelloController::show()` returns a `Response`
6. `Application::finalizeResponse(...)` guarantees an `X-Request-Id` response header
7. `Response::send()` emits the result

---

## Hello World (Internal API)

The internal lane is designed for **signed machine-to-machine JSON requests**. It is stricter than the web lane by design.

A minimal internal endpoint needs:

- a path under **`/internal/v1/*`**
- `request.id`
- `json.body`
- `internal.auth`
- `security.headers`
- a route `scope` attribute
- a controller action that returns `JsonResponse`
- an injected `InternalApiRequestContext`

### Step 1: Add a route in `routes/internal.php`

```php
<?php
declare(strict_types=1);

use App\Controllers\Internal\HelloController;
use MyFrancis\Core\Router;
use MyFrancis\Security\InternalApi\InternalScope;

return static function (Router $router): void {
    $router->get(
        '/internal/v1/hello',
        [HelloController::class, 'show'],
        name: 'internal.v1.hello',
        middleware: ['request.id', 'json.body', 'internal.auth', 'security.headers'],
        attributes: [
            'scope' => InternalScope::HEALTH_READ,
            'expects_json' => true,
        ],
    );
};
```

Why these entries matter:

- `request.id` requires a valid **`X-MF-Request-Id`** on internal routes.
- `json.body` enforces:
  - `Accept: application/json`
  - `Content-Type: application/json; charset=utf-8`
  - no browser cookies
  - valid JSON syntax
  - a top-level JSON object, not a top-level list
- `internal.auth` verifies HMAC credentials, scope, timestamp freshness, and nonce replay protection.
- `security.headers` hardens the response.
- `scope` is required because `InternalApiAuthMiddleware` reads it from the matched `Route`.

> `expects_json` mirrors the built-in internal route files, but there is an important implementation detail: route attributes stay on `MyFrancis\Core\Route`. They are **not** copied onto `Request` automatically. On `/internal/*` routes, JSON error rendering already happens because the path is internal.

### Step 2: Create an internal controller

JSON-only controllers still extend `Controller`, so the base `View` dependency remains part of the constructor contract.

```php
<?php
declare(strict_types=1);

namespace App\Controllers\Internal;

use MyFrancis\Core\Controller;
use MyFrancis\Core\Request;
use MyFrancis\Core\View;
use MyFrancis\Http\JsonResponse;
use MyFrancis\Security\InternalApi\InternalApiRequestContext;

final class HelloController extends Controller
{
    public function __construct(View $view)
    {
        parent::__construct($view);
    }

    public function show(Request $request, InternalApiRequestContext $context): JsonResponse
    {
        return JsonResponse::success([
            'message' => 'Hello from the internal lane.',
            'authenticated_app' => $context->appId,
            'scope' => $context->requiredScope->value,
        ], $request->requestId());
    }
}
```

The key signature is:

```php
public function show(Request $request, InternalApiRequestContext $context): JsonResponse
```

`InternalApiRequestContext` is not passed manually. It becomes injectable because `internal.auth` verifies the request and stores the context in the container for downstream injection.

### Step 3: Sign the request

The easiest way to generate the exact headers expected by the internal lane is `MyFrancis\Security\InternalApi\HmacSigner::buildSignedHeaders(...)`.

```php
<?php
declare(strict_types=1);

use MyFrancis\Core\Request;
use MyFrancis\Security\InternalApi\HmacSigner;

/** @var HmacSigner $signer */
$signer = $application->container()->get(HmacSigner::class);

$request = new Request(
    'GET',
    '/internal/v1/hello',
    serverParameters: ['SCRIPT_NAME' => '/index.php'],
);

$headers = $signer->buildSignedHeaders(
    $request,
    $application->appConfig()->internalApiAppId,
    $application->appConfig()->internalApiKeyId,
    $application->appConfig()->internalApiSecret,
);
```

The fifth, sixth, and seventh parameters let you override the timestamp, nonce, and request ID when you need deterministic test values.

That method builds the exact header set the middleware expects:

- `X-MF-App`
- `X-MF-Key-Id`
- `X-MF-Timestamp`
- `X-MF-Nonce`
- `X-MF-Request-Id`
- `X-MF-Signature`
- `Content-Type: application/json; charset=utf-8`
- `Accept: application/json`

A subtle but important point from `JsonBodyMiddleware`: if a route uses `json.body`, those JSON headers are required **even when the body is empty**, including `GET` requests.

### What a successful response looks like

`JsonResponse::success(...)` wraps your payload in the framework’s standard envelope:

```json
{
  "data": {
    "message": "Hello from the internal lane.",
    "authenticated_app": "<configured-app-id>",
    "scope": "health:read"
  },
  "meta": {
    "request_id": "<request-id>",
    "api_version": "v1"
  }
}
```

The HTTP response also includes:

- `X-Request-Id`
- `Cache-Control: no-store`
- `X-Internal-API-Version: v1`

Notice the naming distinction:

- internal clients **send** `X-MF-Request-Id`
- the framework **responds** with `X-Request-Id`

---

## Choosing the Right Lane

Use the **Web lane** for:

- HTML pages
- form submissions
- session-backed browser interactions
- routes that render views

Use the **Internal lane** for:

- signed service-to-service requests
- JSON-only machine endpoints
- workflows that need replay protection
- routes where cookies must be rejected

A good rule is to choose the lane first, then attach the middleware stack that makes the request behave that way.