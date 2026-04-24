<?php
declare(strict_types=1);

namespace Tests;

final class InvoiceSyncControllerTest extends FrameworkTestCase
{
    /**
     * @return array{
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
    private function validPayload(): array
    {
        return [
            'event_id' => 'evt_01HXABCDEF1234567890',
            'event_type' => 'invoice.paid',
            'source_app' => 'billing',
            'occurred_at' => '2026-04-22T13:27:00Z',
            'invoice' => [
                'id' => 'inv_123',
                'tenant_id' => 'tenant_456',
                'status' => 'paid',
                'currency' => 'CAD',
                'amount_cents' => 12500,
            ],
        ];
    }

    public function testInvoiceSyncRequiresScope(): void
    {
        $application = $this->bootApplication(['INTERNAL_API_SCOPES' => 'session:read']);
        $request = $this->signedInternalRequest(
            $application,
            'POST',
            '/internal/v1/invoices/sync',
            payload: $this->validPayload(),
            extraHeaders: ['Idempotency-Key' => 'idem_scope_test'],
        );

        $response = $application->handle($request);

        self::assertSame(403, $response->statusCode());
    }

    public function testInvoiceSyncRequiresIdempotencyKey(): void
    {
        $application = $this->bootApplication();
        $request = $this->signedInternalRequest($application, 'POST', '/internal/v1/invoices/sync', payload: $this->validPayload());

        $response = $application->handle($request);

        self::assertSame(422, $response->statusCode());
    }

    public function testInvoiceSyncRequiresEventIdAndTenantId(): void
    {
        $application = $this->bootApplication();
        $payload = $this->validPayload();
        $invoice = $payload['invoice'];
        unset($payload['event_id'], $invoice['tenant_id']);
        $payload['invoice'] = $invoice;
        $request = $this->signedInternalRequest(
            $application,
            'POST',
            '/internal/v1/invoices/sync',
            payload: $payload,
            extraHeaders: ['Idempotency-Key' => 'idem_missing_fields'],
        );

        $response = $application->handle($request);

        self::assertSame(422, $response->statusCode());
    }

    public function testInvoiceSyncRejectsInvalidPayloads(): void
    {
        $application = $this->bootApplication();
        $payload = $this->validPayload();
        $invoice = $payload['invoice'];
        $invoice['amount_cents'] = -1;
        $payload['invoice'] = $invoice;
        $request = $this->signedInternalRequest(
            $application,
            'POST',
            '/internal/v1/invoices/sync',
            payload: $payload,
            extraHeaders: ['Idempotency-Key' => 'idem_invalid_payload'],
        );

        $response = $application->handle($request);

        self::assertSame(422, $response->statusCode());
    }

    public function testDuplicateInvoiceEventsFailClosed(): void
    {
        $application = $this->bootApplication();
        $payload = $this->validPayload();

        $firstRequest = $this->signedInternalRequest(
            $application,
            'POST',
            '/internal/v1/invoices/sync',
            payload: $payload,
            extraHeaders: ['Idempotency-Key' => 'idem_invoice_1'],
        );
        $secondRequest = $this->signedInternalRequest(
            $application,
            'POST',
            '/internal/v1/invoices/sync',
            payload: $payload,
            extraHeaders: ['Idempotency-Key' => 'idem_invoice_2'],
        );

        $firstResponse = $application->handle($firstRequest);
        $secondResponse = $application->handle($secondRequest);

        self::assertSame(200, $firstResponse->statusCode());
        self::assertSame(409, $secondResponse->statusCode());
        self::assertStringContainsString('"duplicate_event"', $secondResponse->body());
    }
}
