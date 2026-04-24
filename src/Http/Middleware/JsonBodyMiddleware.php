<?php
declare(strict_types=1);

namespace MyFrancis\Http\Middleware;

use JsonException;
use MyFrancis\Core\Exceptions\BadRequestHttpException;
use MyFrancis\Core\Exceptions\NotAcceptableHttpException;
use MyFrancis\Core\Exceptions\UnsupportedMediaTypeHttpException;
use MyFrancis\Core\Request;
use MyFrancis\Core\Response;
use MyFrancis\Support\Logger;

final class JsonBodyMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Logger $logger)
    {
    }

    /**
     * @param callable(Request): Response $next
     */
    #[\Override]
    public function process(Request $request, callable $next): Response
    {
        $this->assertNoCookies($request);
        $this->assertAcceptHeader($request);
        $this->assertContentTypeHeader($request);

        $rawBody = $request->rawBody();

        if ($rawBody === '') {
            return $next($request->withJsonPayload([]));
        }

        if (! $this->isValidJson($rawBody)) {
            $this->logger->warning('Internal API request rejected due to invalid JSON.', [
                'request_id' => $request->requestId(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            throw new BadRequestHttpException('The JSON request body is invalid.');
        }

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestHttpException('The JSON request body is invalid.');
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new BadRequestHttpException('The JSON request body must decode to an object.');
        }

        return $next($request->withJsonPayload($decoded));
    }

    private function assertNoCookies(Request $request): void
    {
        $cookieHeader = $this->headerValue($request, 'cookie');

        if ($cookieHeader !== '' || $request->cookie() !== []) {
            throw new BadRequestHttpException('Browser cookies are not allowed on internal API routes.');
        }
    }

    private function assertAcceptHeader(Request $request): void
    {
        $accept = strtolower($this->headerValue($request, 'accept'));

        if (! str_contains($accept, 'application/json')) {
            throw new NotAcceptableHttpException('The Accept header must allow application/json.');
        }
    }

    private function assertContentTypeHeader(Request $request): void
    {
        $contentType = strtolower($this->headerValue($request, 'content-type'));

        if ($contentType === '') {
            throw new UnsupportedMediaTypeHttpException('The Content-Type header must be application/json; charset=utf-8.');
        }

        $segments = array_map('trim', explode(';', $contentType));
        $mediaType = array_shift($segments);

        if ($mediaType !== 'application/json') {
            throw new UnsupportedMediaTypeHttpException('The Content-Type header must be application/json; charset=utf-8.');
        }

        /** @var array<string, string> $parameters */
        $parameters = [];

        foreach ($segments as $segment) {
            if (! str_contains($segment, '=')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode('=', $segment, 2));
            $parameters[strtolower($name)] = trim($value, "\"'");
        }

        if (($parameters['charset'] ?? null) !== 'utf-8') {
            throw new UnsupportedMediaTypeHttpException('The Content-Type header must be application/json; charset=utf-8.');
        }
    }

    private function isValidJson(string $json): bool
    {
        if (function_exists('json_validate')) {
            return json_validate($json);
        }

        json_decode($json);

        return json_last_error() === JSON_ERROR_NONE;
    }

    private function headerValue(Request $request, string $name): string
    {
        $value = $request->header($name, '');

        return is_string($value) ? trim($value) : '';
    }
}
