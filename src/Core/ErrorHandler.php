<?php
declare(strict_types=1);

namespace MyFrancis\Core;

use ErrorException;
use MyFrancis\Config\AppConfig;
use MyFrancis\Core\Enums\HttpStatus;
use MyFrancis\Core\Exceptions\HttpException;
use MyFrancis\Http\JsonResponse;
use MyFrancis\Security\Escaper;
use MyFrancis\Support\Logger;
use Throwable;

final class ErrorHandler
{
    private static bool $registered = false;

    public function __construct(
        private readonly AppConfig $appConfig,
        private readonly Escaper $escaper,
        private readonly Logger $logger,
    ) {
    }

    public function register(): void
    {
        if (self::$registered) {
            return;
        }

        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        self::$registered = true;
    }

    public static function reset(): void
    {
        self::$registered = false;
    }

    public function handle(Request $request, Throwable $exception): Response
    {
        $this->report($request, $exception);

        if ($exception instanceof HttpException) {
            return $this->renderHttpException($request, $exception);
        }

        return $this->renderThrowable($request, $exception);
    }

    private function report(Request $request, Throwable $exception): void
    {
        $statusCode = $exception instanceof HttpException
            ? $exception->status()->value
            : HttpStatus::INTERNAL_SERVER_ERROR->value;

        $context = [
            'request_id' => $request->requestId(),
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'status' => $statusCode,
            'exception_class' => $exception::class,
        ];

        if ($statusCode >= HttpStatus::INTERNAL_SERVER_ERROR->value) {
            $this->logger->error('Unhandled application exception.', $context);
            return;
        }

        $this->logger->warning('HTTP exception handled.', $context);
    }

    private function renderHttpException(Request $request, HttpException $exception): Response
    {
        $message = $this->safeHttpMessage($exception);

        if ($this->expectsJson($request)) {
            return JsonResponse::error(
                $this->errorCodeForStatus($exception->status()),
                $message,
                $request->requestId(),
                $exception->status(),
                $exception->headers(),
            );
        }

        return Response::html(
            $this->renderHtmlDocument(
                $exception->status()->reasonPhrase(),
                $message,
                $request->requestId(),
            ),
            $exception->status(),
            $exception->headers(),
        );
    }

    private function renderThrowable(Request $request, Throwable $exception): Response
    {
        $message = 'An internal server error occurred.';

        if ($this->appConfig->isDebugMode()) {
            $message .= sprintf(' [%s]', $exception::class);
        }

        if ($this->expectsJson($request)) {
            return JsonResponse::error(
                'internal_server_error',
                $message,
                $request->requestId(),
                HttpStatus::INTERNAL_SERVER_ERROR,
            );
        }

        return Response::html(
            $this->renderHtmlDocument(
                HttpStatus::INTERNAL_SERVER_ERROR->reasonPhrase(),
                $message,
                $request->requestId(),
            ),
            HttpStatus::INTERNAL_SERVER_ERROR,
        );
    }

    private function expectsJson(Request $request): bool
    {
        $acceptHeader = $request->header('accept', '');
        $accept = is_string($acceptHeader) ? strtolower($acceptHeader) : '';

        return str_starts_with($request->path(), '/internal/')
            || str_contains($accept, 'application/json')
            || $request->attribute('expects_json', false) === true;
    }

    private function safeHttpMessage(HttpException $exception): string
    {
        if ($exception->status()->value >= HttpStatus::INTERNAL_SERVER_ERROR->value) {
            return $this->appConfig->isDebugMode()
                ? sprintf('An internal server error occurred. [%s]', $exception::class)
                : 'An internal server error occurred.';
        }

        return $exception->getMessage();
    }

    private function errorCodeForStatus(HttpStatus $status): string
    {
        return match ($status) {
            HttpStatus::BAD_REQUEST => 'bad_request',
            HttpStatus::UNAUTHORIZED => 'unauthorized',
            HttpStatus::FORBIDDEN => 'forbidden',
            HttpStatus::NOT_FOUND => 'not_found',
            HttpStatus::METHOD_NOT_ALLOWED => 'method_not_allowed',
            HttpStatus::NOT_ACCEPTABLE => 'not_acceptable',
            HttpStatus::CONFLICT => 'conflict',
            HttpStatus::UNSUPPORTED_MEDIA_TYPE => 'unsupported_media_type',
            HttpStatus::UNPROCESSABLE_ENTITY => 'validation_error',
            default => 'internal_server_error',
        };
    }

    private function renderHtmlDocument(string $title, string $message, string $requestId): string
    {
        return sprintf(
            '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>%s</title></head><body><main><h1>%s</h1><p>%s</p><p>Request ID: <code>%s</code></p></main></body></html>',
            $this->escaper->html($title),
            $this->escaper->html($title),
            $this->escaper->html($message),
            $this->escaper->html($requestId),
        );
    }
}
