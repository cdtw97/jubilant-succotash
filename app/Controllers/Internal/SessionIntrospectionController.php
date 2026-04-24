<?php
declare(strict_types=1);

namespace App\Controllers\Internal;

use MyFrancis\Core\Controller;
use MyFrancis\Core\Enums\HttpStatus;
use MyFrancis\Core\Request;
use MyFrancis\Core\View;
use MyFrancis\Http\JsonResponse;
use MyFrancis\Security\InternalApi\InternalApiRequestContext;

final class SessionIntrospectionController extends Controller
{
    private const PLACEHOLDER_EXPIRES_AT = '2026-04-22T15:00:00Z';

    public function __construct(View $view)
    {
        parent::__construct($view);
    }

    public function store(Request $request, InternalApiRequestContext $context): JsonResponse
    {
        $payload = $request->json();

        if (! is_array($payload) || array_is_list($payload)) {
            return JsonResponse::error(
                'validation_error',
                'The request payload is invalid.',
                $request->requestId(),
                HttpStatus::UNPROCESSABLE_ENTITY,
            );
        }

        $sessionTokenHash = $payload['session_token_hash'] ?? null;
        $requestedScopes = $payload['requested_scopes'] ?? null;

        if (! is_string($sessionTokenHash) || trim($sessionTokenHash) === '') {
            return JsonResponse::error(
                'validation_error',
                'The request payload is invalid.',
                $request->requestId(),
                HttpStatus::UNPROCESSABLE_ENTITY,
            );
        }

        if (! is_array($requestedScopes) || ! $this->isStringList($requestedScopes)) {
            return JsonResponse::error(
                'validation_error',
                'The request payload is invalid.',
                $request->requestId(),
                HttpStatus::UNPROCESSABLE_ENTITY,
            );
        }

        return JsonResponse::success([
            'active' => true,
            'user' => [
                'id' => 'usr_123',
                'email' => 'user@example.com',
                'display_name' => 'Jane Doe',
            ],
            'tenant' => [
                'id' => 'tenant_456',
            ],
            'roles' => ['owner'],
            'scopes' => array_values($requestedScopes),
            'expires_at' => self::PLACEHOLDER_EXPIRES_AT,
            'authenticated_app' => $context->appId,
        ], $request->requestId());
    }

    /**
     * @param array<int|string, mixed> $values
     */
    private function isStringList(array $values): bool
    {
        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }
}
