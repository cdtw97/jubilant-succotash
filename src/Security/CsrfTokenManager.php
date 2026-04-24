<?php
declare(strict_types=1);

namespace MyFrancis\Security;

final class CsrfTokenManager
{
    private const SESSION_KEY = '_csrf_token';

    public function __construct(
        private readonly SessionManager $sessionManager,
        private readonly Escaper $escaper,
    ) {
    }

    public function generateToken(bool $forceRefresh = false): string
    {
        if (! $forceRefresh) {
            $existingToken = $this->sessionManager->get(self::SESSION_KEY);

            if (is_string($existingToken) && $existingToken !== '') {
                return $existingToken;
            }
        }

        $token = bin2hex(random_bytes(32));
        $this->sessionManager->put(self::SESSION_KEY, $token);

        return $token;
    }

    public function validateToken(?string $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        $storedToken = $this->sessionManager->get(self::SESSION_KEY);

        return is_string($storedToken) && hash_equals($storedToken, $token);
    }

    public function field(string $fieldName = '_token'): string
    {
        $token = $this->generateToken();

        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            $this->escaper->attr($fieldName),
            $this->escaper->attr($token),
        );
    }
}
