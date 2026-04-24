<?php
declare(strict_types=1);

namespace MyFrancis\Security\InternalApi;

enum InternalScope: string
{
    case SESSION_READ = 'session:read';
    case INVOICE_READ = 'invoice:read';
    case INVOICE_WRITE = 'invoice:write';
    case INVOICE_SYNC = 'invoice:sync';
    case HEALTH_READ = 'health:read';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $scope): string => $scope->value,
            self::cases(),
        );
    }
}
