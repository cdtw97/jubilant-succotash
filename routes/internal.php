<?php
declare(strict_types=1);

use App\Controllers\Internal\HealthController;
use App\Controllers\Internal\InvoiceSyncController;
use App\Controllers\Internal\SessionIntrospectionController;
use MyFrancis\Core\Router;
use MyFrancis\Security\InternalApi\InternalScope;

return static function (Router $router): void {
    $router->get(
        '/internal/v1/health',
        [HealthController::class, 'show'],
        name: 'internal.v1.health',
        middleware: ['request.id', 'json.body', 'internal.auth', 'security.headers'],
        attributes: [
            'scope' => InternalScope::HEALTH_READ,
            'expects_json' => true,
        ],
    );

    $router->post(
        '/internal/v1/session/introspect',
        [SessionIntrospectionController::class, 'store'],
        name: 'internal.v1.session.introspect',
        middleware: ['request.id', 'json.body', 'internal.auth', 'security.headers'],
        attributes: [
            'scope' => InternalScope::SESSION_READ,
            'expects_json' => true,
        ],
    );

    $router->post(
        '/internal/v1/invoices/sync',
        [InvoiceSyncController::class, 'store'],
        name: 'internal.v1.invoices.sync',
        middleware: ['request.id', 'json.body', 'internal.auth', 'security.headers'],
        attributes: [
            'scope' => InternalScope::INVOICE_SYNC,
            'expects_json' => true,
        ],
    );
};
