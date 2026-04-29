<?php
declare(strict_types=1);

namespace App\Middleware;

use JsonException;
use MyFrancis\Core\Exceptions\BadRequestHttpException;
use MyFrancis\Core\Exceptions\UnsupportedMediaTypeHttpException;
use MyFrancis\Core\Request;
use MyFrancis\Core\Response;
use MyFrancis\Http\Middleware\MiddlewareInterface;
use MyFrancis\Support\Logger;

final class WebJsonInputMiddleware implements MiddlewareInterface
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
        $rawBody = trim($request->rawBody());

        if ($rawBody === '') {
            return $next($request->withJsonPayload([]));
        }

        $this->assertJsonContentType($request);

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->logger->warning('Web JSON request rejected due to invalid JSON.', [
                'request_id' => $request->requestId(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            throw new BadRequestHttpException('The JSON request body is invalid.');
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new BadRequestHttpException('The JSON request body must decode to an object.');
        }

        return $next($request->withJsonPayload($decoded));
    }

    private function assertJsonContentType(Request $request): void
    {
        $contentType = strtolower(trim((string) $request->header('content-type', '')));

        if ($contentType === '') {
            throw new UnsupportedMediaTypeHttpException('The Content-Type header must be application/json.');
        }

        $segments = array_map('trim', explode(';', $contentType));
        $mediaType = array_shift($segments);

        if ($mediaType !== 'application/json') {
            throw new UnsupportedMediaTypeHttpException('The Content-Type header must be application/json.');
        }
    }
}
