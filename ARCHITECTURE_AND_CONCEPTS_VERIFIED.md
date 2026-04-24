# Architecture and Concepts

## Architectural Summary

This framework is a compact **front controller + router + middleware pipeline** with MVC conveniences on top.

The central mental model is:

1. one front controller receives every request
2. the router finds an explicit route
3. the route’s middleware stack activates request semantics
4. the router invokes a closure or controller method through reflection
5. the action returns a `Response` or an HTML string
6. the application finalizes and sends the response

MVC exists, but the request pipeline is the real center of gravity.

---

## The Request Lifecycle

### Lifecycle diagram

```mermaid
flowchart TD
    A[HTTP request] --> B[public/index.php]
    B --> C[require vendor/autoload.php]
    B --> D[require app/bootstrap.php]
    D --> E[new Application(...)]
    E --> F[register core services, middleware aliases, and ErrorHandler]
    B --> G[Application::loadRoutes(web.php)]
    G --> H[Application::loadRoutes(internal.php)]
    H --> I[Request::capture()]
    I --> J[Application::handle(Request)]
    J --> K[Container::set(Request::class, Request)]
    K --> L[Router::dispatch(Request, Container)]
    L --> M{matching Route?}
    M -- no path match --> N[NotFoundHttpException]
    M -- path match but wrong method --> O[MethodNotAllowedHttpException]
    M -- match --> P[Container::set(Route::class, Route)]
    P --> Q[Build middleware pipeline]
    Q --> R[Middleware 1]
    R --> S[Middleware 2]
    S --> T[...]
    T --> U[invokeAction()]
    U --> V{Closure or controller method}
    V --> W[Resolve controller from Container]
    W --> X[Inject non-builtin typed parameters]
    X --> Y[Bind builtin route parameters by name]
    Y --> Z[Return Response or HTML string]
    N --> AA[ErrorHandler::handle(current Request, Throwable)]
    O --> AA
    R -. exception .-> AA
    S -. exception .-> AA
    U -. exception .-> AA
    Z --> AB[Application::finalizeResponse]
    AA --> AB
    AB --> AC[Response::send()]
```

### 1. Front controller

`public/index.php` is the single public entry point. It does not contain domain logic. It:

- loads Composer autoloading
- requires `app/bootstrap.php`
- loads `routes/web.php`
- loads `routes/internal.php`
- captures the request with `Request::capture()`
- sends the response returned by `Application::handle(...)`

The source code is deliberately direct:

```php
$application
    ->loadRoutes($basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php')
    ->loadRoutes($basePath . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'internal.php')
    ->handle(Request::capture())
    ->send();
```

### 2. Application boot

`app/bootstrap.php` creates the container and returns:

```php
new Application(
    $basePath,
    $container,
    AppConfig::fromEnv(),
    DatabaseConfig::fromEnv(),
)
```

Inside `MyFrancis\Core\Application::__construct(...)`, the framework immediately:

- creates the `Router`
- stores the singleton instance
- registers core services in the `Container`
- registers middleware aliases
- registers the error handler

This is the framework’s composition root.

### 3. Routing and dispatch

`MyFrancis\Core\Router` stores routes in declaration order and dispatches them explicitly.

For each route:

1. `Route::match()` checks the normalized request path
2. if the path matches but the method does not, the router records the allowed verb
3. if the path and method both match, dispatch stops immediately and that route wins
4. if at least one path matched with a different method, the router throws `MethodNotAllowedHttpException`
5. otherwise it throws `NotFoundHttpException`

That yields predictable `404` and `405` behavior without dynamic controller discovery.

### 4. Middleware pipeline

Once a route matches, the router wraps the destination action in the route’s middleware stack.

A route entry can be:

- a string alias such as `request.id`
- a `MiddlewareInterface` instance
- a closure with the signature `function (Request $request, callable $next): Response`

Middleware executes in the **same order it is declared** on the route. The router reverses the array only to build the nested pipeline correctly.

For example, this stack:

```php
middleware: ['request.id', 'json.body', 'internal.auth', 'security.headers']
```

executes in this order:

1. `request.id`
2. `json.body`
3. `internal.auth`
4. `security.headers`
5. the controller action

That order matters:

- `request.id` must run before `internal.auth`, because `HmacVerifier` compares `X-MF-Request-Id` to `Request::requestId()`
- `json.body` must run before a controller can rely on `Request::json()`

### 5. Request mutation is clone-style

`MyFrancis\Core\Request` is passed forward as a new object when middleware changes it.

The key methods are:

- `withRequestId(string $requestId): self`
- `withJsonPayload(array $jsonPayload): self`
- `withAttribute(string $key, mixed $value): self`

The router updates the container with the current `Request` before each middleware hop, so downstream middleware and the error handler can see the latest request object.

### 6. Action invocation

After middleware passes, the router invokes either:

- a route closure, or
- a controller method defined as `[ControllerClass::class, 'methodName']`

The routing rules are intentionally strict:

- the target method must be `public`
- the method must be declared on the target controller class itself
- inherited controller methods cannot be exposed as routes

That last rule is enforced directly in `Router::invokeAction(...)`.

### 7. Result normalization

The router accepts only two successful action result shapes:

- a `Response`
- an HTML `string`

If the action returns a string, the router wraps it with `Response::html(...)`.

If the action returns anything else, the router throws a `ConfigurationException`. Arrays are **not** auto-converted to JSON.

### 8. Finalization and error handling

`Application::handle(Request $request): Response` is the top-level execution boundary.

After dispatch completes, `Application::finalizeResponse(...)` adds cross-cutting headers:

- `X-Request-Id` on every response
- `Cache-Control: no-store` on `/internal/*`
- `X-Internal-API-Version: v1` on `/internal/*`

If anything throws during dispatch, `Application::handle(...)` retrieves the current `Request` from the container and delegates to `MyFrancis\Core\ErrorHandler`.

`ErrorHandler` decides to render JSON when any of these are true:

- the path starts with `/internal/`
- the `Accept` header contains `application/json`
- `Request::attribute('expects_json')` is `true`

One important implementation detail: route attributes are not copied onto the request automatically. A route attribute such as `expects_json` remains metadata on `MyFrancis\Core\Route` unless middleware explicitly adds it to the request with `Request::withAttribute(...)`.

---

## Dependency Injection

### The container API

`MyFrancis\Core\Container` is intentionally small:

- `set(string $id, mixed $value): void`
- `factory(string $id, Closure $factory): void`
- `get(string $id): mixed`
- `make(string $id): object`

`get(...)` resolves and caches entries. If an ID has no entry or factory but names an instantiable class, `make(...)` autowires it with reflection.

### Constructor injection

Constructor autowiring works for **non-builtin named types** that the container can resolve.

This exact constructor from `InvoiceSyncController` is valid because both dependencies are resolvable:

```php
public function __construct(
    View $view,
    private readonly NonceStore $nonceStore,
) {
    parent::__construct($view);
}
```

### Action injection

Controller action parameters are resolved with two rules:

1. **non-builtin named types** come from the container
2. **builtin parameters** are bound from route parameters by matching the parameter name

Two exact source examples illustrate both sides:

```php
public function show(Request $request, InternalApiRequestContext $context): JsonResponse
```

from `HealthController`, and:

```php
public function user(int $id): Response
```

from `Tests\Fixtures\Controllers\FixtureController`.

What that means in practice:

- `Request` is injected from the container
- `InternalApiRequestContext` is injected from the container after `internal.auth` stores it
- `id` is taken from a route placeholder such as `/users/{id}` and cast to `int`

Built-in route parameters are cast when the parameter type is one of:

- `int`
- `float`
- `bool`
- `string`

### What the container will not infer

Autowiring is deliberately conservative.

The container does **not** infer:

- interfaces unless you bind them explicitly with `set()` or `factory()`
- scalar constructor parameters without defaults
- missing builtin action parameters

Those cases fail in different places:

- interfaces fail in `Container::get(...)` with `Service [...] is not defined.`
- unresolved scalar constructor parameters fail in `Container::make(...)`
- unresolved builtin action parameters fail in `Router::invokeReflection(...)`

---

## The Container as a Request-Scoped Context Store

The container is not only a service registry. During a request, it also acts as a **request-scoped context store**.

The framework writes request-specific objects into the container as the pipeline advances:

- `Application::handle(...)` stores the current `Request`
- `Router::dispatch(...)` stores the matched `Route`
- `InternalApiAuthMiddleware` stores `InternalApiRequestContext`

That is why this action signature works:

```php
public function show(Request $request, InternalApiRequestContext $context): JsonResponse
```

`InternalApiRequestContext` is created during middleware, then injected into the controller as if it were a regular service.

This is one of the framework’s main extension points: middleware can compute typed context objects and place them in the container for downstream consumption.

---

## Route Metadata and Attributes

Routes accept an `attributes` array:

```php
attributes: [
    'scope' => InternalScope::HEALTH_READ,
    'expects_json' => true,
]
```

Those values live on `MyFrancis\Core\Route` and are read with:

```php
$route->attribute('scope');
```

That is exactly how `InternalApiAuthMiddleware` discovers the required internal scope.

The important limitation is that route attributes are **not** copied onto `Request`. If you want request attributes, add middleware that calls `Request::withAttribute(...)` and passes the new request forward.

---

## The Two-Lane System

| Aspect | Web lane | Internal lane |
|---|---|---|
| Typical paths | Explicit browser routes such as `/` and `/about` | `/internal/v1/*` |
| Primary output | HTML pages | JSON envelopes |
| Common controller return type | `Response` | `JsonResponse` |
| Inbound request ID header | Optional `X-Request-Id` | Required `X-MF-Request-Id` |
| Response request ID header | `X-Request-Id` | `X-Request-Id` |
| Request parsing | Query parameters, form input, files, raw body | JSON-only semantics if `json.body` is attached |
| Anti-forgery / auth model | CSRF for unsafe methods, plus app-level auth you add | HMAC signature, timestamp, nonce, and scope |
| Cookies | Allowed when session-backed behavior is needed | Rejected by `json.body` |
| Session behavior | Lazy-started by session-dependent services such as CSRF | Not used for authentication |
| Default middleware shape | `request.id`, `security.headers`, `csrf` | `request.id`, `json.body`, `internal.auth`, `security.headers` |
| Final response additions | `X-Request-Id` | `X-Request-Id`, `Cache-Control: no-store`, `X-Internal-API-Version: v1` |
| Error rendering | HTML by default, JSON if the request asks for it | JSON by default because the path is `/internal/*` |
| Route metadata | Usually minimal | Built-in routes include `scope` and `expects_json`; core middleware consumes `scope` |

The two lanes share the same router, request object, controller base class, and response model. The middleware stack is what changes the meaning of the request.

---

## Security and Nonces

### Internal HMAC verification

Internal routes rely on:

- `MyFrancis\Security\InternalApi\HmacSigner`
- `MyFrancis\Security\InternalApi\HmacVerifier`
- `MyFrancis\Security\InternalApi\InternalApiAuthMiddleware`

The canonical request string is:

```text
METHOD
/path
canonical_sorted_query_string
X-MF-Timestamp
X-MF-Nonce
sha256(raw_body)
```

The signature is:

```text
base64url(hmac_sha256(canonical_request, shared_secret))
```

Important verifier rules from the source:

- `X-MF-App` must match `AppConfig::internalApiAppId`
- `X-MF-Key-Id` must match `AppConfig::internalApiKeyId`
- `X-MF-Request-Id` must match `Request::requestId()`
- the timestamp must parse and be within a **300-second** skew window
- the nonce must match the allowed format and must not have been seen already
- the signature is compared with `hash_equals(...)`
- the matched route’s `scope` must be present in `AppConfig::internalApiScopes`
- in production, `internal.auth` requires HTTPS

`HmacSigner::canonicalSortedQueryString(...)` recursively sorts associative query arrays and builds the canonical query with `http_build_query(..., PHP_QUERY_RFC3986)`, which keeps signing deterministic.

### File-backed nonce storage

Replay protection is implemented by `MyFrancis\Security\InternalApi\NonceStore`.

Its storage model is pragmatic and local:

- files are stored in `storage/framework/nonces`
- the key becomes `hash('sha256', $key) . '.json'`
- the file is opened with `fopen(..., 'c+b')`
- the file is locked with `flock(..., LOCK_EX)`
- the payload stores `expires_at`
- if an unexpired record already exists, `remember(...)` returns `false`
- otherwise the new expiry is written and `remember(...)` returns `true`

This gives the framework:

- replay protection without Redis or another external cache
- safe concurrent writes on a single host
- a simple TTL model based on the expiry timestamp

For internal HMAC verification, the stored key format is:

```text
internal-api:{appId}:{keyId}:{nonce}
```

That scopes replay protection to the calling application, key, and nonce combination.

### The same store is reused for idempotency

`App\Controllers\Internal\InvoiceSyncController` reuses the same store to reject duplicate invoice events:

```php
$this->nonceStore->remember(
    'invoice-event:' . $eventId,
    (new DateTimeImmutable('now'))->modify('+1 day'),
)
```

So `NonceStore` is really an expiring key store. Internal HMAC replay protection is simply its first built-in use case.