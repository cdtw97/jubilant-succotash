<?php
declare(strict_types=1);

namespace App\Controllers\Internal;

use DateTimeImmutable;
use MyFrancis\Core\Controller;
use MyFrancis\Core\Enums\HttpStatus;
use MyFrancis\Core\Request;
use MyFrancis\Core\View;
use MyFrancis\Http\JsonResponse;
use MyFrancis\Security\InternalApi\InternalApiRequestContext;
use MyFrancis\Security\InternalApi\NonceStore;

/**
 * @phpstan-type InvoicePayload array{
 *     event_id: string,
 *     event_type: string,
 *     source_app: string,
 *     occurred_at: string,
 *     invoice: array{
 *         id: string,
 *         tenant_id: string,
 *         status: string,
 *         currency: string,
 *         amount_cents: int
 *     }
 * }
 */
final class InvoiceSyncController extends Controller
{
    public function __construct(
        View $view,
        private readonly NonceStore $nonceStore,
    ) {
        parent::__construct($view);
    }

    public function store(Request $request, InternalApiRequestContext $context): JsonResponse
    {
        $payload = $request->json();
        $idempotencyKey = $request->header('idempotency-key');

        if (! is_string($idempotencyKey) || trim($idempotencyKey) === '') {
            return JsonResponse::error(
                'validation_error',
                'The invoice sync payload is invalid.',
                $request->requestId(),
                HttpStatus::UNPROCESSABLE_ENTITY,
            );
        }

        if (! is_array($payload) || array_is_list($payload)) {
            return JsonResponse::error(
                'validation_error',
                'The invoice sync payload is invalid.',
                $request->requestId(),
                HttpStatus::UNPROCESSABLE_ENTITY,
            );
        }

        $normalizedPayload = $this->normalizePayload($payload);

        if ($normalizedPayload === null) {
            return JsonResponse::error(
                'validation_error',
                'The invoice sync payload is invalid.',
                $request->requestId(),
                HttpStatus::UNPROCESSABLE_ENTITY,
            );
        }

        $eventId = $normalizedPayload['event_id'];
        $invoice = $normalizedPayload['invoice'];

        if (! $this->nonceStore->remember('invoice-event:' . $eventId, (new DateTimeImmutable('now'))->modify('+1 day'))) {
            return JsonResponse::error(
                'duplicate_event',
                'This invoice event has already been processed.',
                $request->requestId(),
                HttpStatus::CONFLICT,
            );
        }

        return JsonResponse::success([
            'accepted' => true,
            'invoice_id' => $invoice['id'],
            'authenticated_app' => $context->appId,
        ], $request->requestId());
    }

    /**
     * @param array<int|string, mixed> $payload
     * @return InvoicePayload|null
     */
    private function normalizePayload(array $payload): ?array
    {
        if (! isset($payload['event_id'], $payload['event_type'], $payload['source_app'], $payload['occurred_at'], $payload['invoice'])) {
            return null;
        }

        if (! is_string($payload['event_id']) || trim($payload['event_id']) === '') {
            return null;
        }

        if (! is_string($payload['event_type']) || trim($payload['event_type']) === '') {
            return null;
        }

        if (! is_string($payload['source_app']) || trim($payload['source_app']) === '') {
            return null;
        }

        if (! is_string($payload['occurred_at']) || trim($payload['occurred_at']) === '') {
            return null;
        }

        try {
            new DateTimeImmutable($payload['occurred_at']);
        } catch (\Exception) {
            return null;
        }

        if (! is_array($payload['invoice']) || array_is_list($payload['invoice'])) {
            return null;
        }

        $invoice = $payload['invoice'];

        if (
            ! isset($invoice['id'], $invoice['tenant_id'], $invoice['status'], $invoice['currency'], $invoice['amount_cents'])
            || ! is_string($invoice['id']) || trim($invoice['id']) === ''
            || ! is_string($invoice['tenant_id']) || trim($invoice['tenant_id']) === ''
            || ! is_string($invoice['status']) || trim($invoice['status']) === ''
            || ! is_string($invoice['currency']) || trim($invoice['currency']) === ''
            || ! is_int($invoice['amount_cents']) || $invoice['amount_cents'] < 0
        ) {
            return null;
        }

        return [
            'event_id' => $payload['event_id'],
            'event_type' => $payload['event_type'],
            'source_app' => $payload['source_app'],
            'occurred_at' => $payload['occurred_at'],
            'invoice' => [
                'id' => $invoice['id'],
                'tenant_id' => $invoice['tenant_id'],
                'status' => $invoice['status'],
                'currency' => $invoice['currency'],
                'amount_cents' => $invoice['amount_cents'],
            ],
        ];
    }
}
