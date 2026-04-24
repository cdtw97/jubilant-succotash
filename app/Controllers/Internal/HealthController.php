<?php
declare(strict_types=1);

namespace App\Controllers\Internal;

use DateTimeImmutable;
use MyFrancis\Config\AppConfig;
use MyFrancis\Core\Controller;
use MyFrancis\Core\Request;
use MyFrancis\Core\View;
use MyFrancis\Http\JsonResponse;
use MyFrancis\Security\InternalApi\InternalApiRequestContext;

final class HealthController extends Controller
{
    public function __construct(
        View $view,
        private readonly AppConfig $appConfig,
    ) {
        parent::__construct($view);
    }

    public function show(Request $request, InternalApiRequestContext $context): JsonResponse
    {
        return JsonResponse::success([
            'status' => 'ok',
            'app' => $this->appConfig->name,
            'environment' => $this->appConfig->environment->value,
            'authenticated_app' => $context->appId,
            'timestamp' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
        ], $request->requestId());
    }
}
