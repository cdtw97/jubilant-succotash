<?php
declare(strict_types=1);

namespace MyFrancis\Security\InternalApi;

use DateTimeImmutable;

readonly class InternalApiRequestContext
{
    /**
     * @param list<string> $grantedScopes
     */
    public function __construct(
        public string $appId,
        public string $keyId,
        public string $requestId,
        public string $nonce,
        public string $signature,
        public string $bodyHash,
        public DateTimeImmutable $timestamp,
        public InternalScope $requiredScope,
        public array $grantedScopes,
    ) {
    }

    public function hasScope(InternalScope $scope): bool
    {
        return in_array($scope->value, $this->grantedScopes, true);
    }
}
