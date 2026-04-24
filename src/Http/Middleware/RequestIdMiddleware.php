<?php
declare(strict_types=1);

namespace MyFrancis\Http\Middleware;

use MyFrancis\Core\Exceptions\BadRequestHttpException;
use MyFrancis\Core\Request;
use MyFrancis\Core\Response;
use MyFrancis\Support\Logger;

final class RequestIdMiddleware implements MiddlewareInterface
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
        $requestId = $request->requestId();

        if ($this->isInternalRequest($request)) {
            $internalRequestId = $this->headerValue($request, 'x-mf-request-id');

            if ($internalRequestId === '' || ! Request::isValidRequestId($internalRequestId)) {
                $this->logger->warning('Internal API request rejected due to invalid request id.', [
                    'request_id' => $requestId,
                    'path' => $request->path(),
                    'method' => $request->method(),
                ]);

                throw new BadRequestHttpException('A valid X-MF-Request-Id header is required.');
            }

            $requestId = $internalRequestId;
        } else {
            $incomingRequestId = $this->headerValue($request, 'x-request-id');

            if ($incomingRequestId !== '') {
                if (Request::isValidRequestId($incomingRequestId)) {
                    $requestId = $incomingRequestId;
                } else {
                    $this->logger->warning('Ignoring invalid external request id header.', [
                        'request_id' => $requestId,
                        'path' => $request->path(),
                        'method' => $request->method(),
                    ]);
                }
            } else {
                $requestId = Request::generateRequestId();
            }
        }

        $request = $request->withRequestId($requestId);

        return $next($request)->withHeader('X-Request-Id', $request->requestId());
    }

    private function isInternalRequest(Request $request): bool
    {
        return str_starts_with($request->path(), '/internal/');
    }

    private function headerValue(Request $request, string $name): string
    {
        $value = $request->header($name, '');

        return is_string($value) ? trim($value) : '';
    }
}
