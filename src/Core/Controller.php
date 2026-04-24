<?php
declare(strict_types=1);

namespace MyFrancis\Core;

use MyFrancis\Core\Enums\HttpStatus;
use MyFrancis\Http\JsonResponse;

abstract class Controller
{
    public function __construct(protected readonly View $viewRenderer)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    protected function view(
        string $view,
        array $data = [],
        HttpStatus|int $status = HttpStatus::OK,
        array $headers = [],
    ): Response {
        return Response::html(
            $this->viewRenderer->render($view, $data),
            $status,
            $headers,
        );
    }

    /**
     * @param array<string, string> $headers
     */
    protected function json(
        mixed $data,
        Request $request,
        HttpStatus|int $status = HttpStatus::OK,
        array $headers = [],
    ): JsonResponse {
        return JsonResponse::success($data, $request->requestId(), status: $status, headers: $headers);
    }
}
