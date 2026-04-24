<?php
declare(strict_types=1);

namespace MyFrancis\Config;

use MyFrancis\Security\InternalApi\InternalScope;
use MyFrancis\Support\Env;

readonly class AppConfig
{
    /**
     * @param list<string> $internalApiScopes
     */
    public function __construct(
        public string $name,
        public string $url,
        public Environment $environment,
        public bool $debug,
        public string $sessionName,
        public bool $sessionSecure,
        public string $internalApiAppId,
        public string $internalApiKeyId,
        public string $internalApiSecret,
        public array $internalApiScopes,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            name: Env::string('APP_NAME', 'MyFrancis Base Core'),
            url: rtrim(Env::string('APP_URL', 'http://localhost'), '/'),
            environment: Environment::fromString(Env::string('APP_ENV', 'production')),
            debug: Env::bool('APP_DEBUG', false),
            sessionName: Env::string('SESSION_NAME', 'mf_session'),
            sessionSecure: Env::bool('SESSION_SECURE', false),
            internalApiAppId: Env::string('INTERNAL_API_APP_ID', 'base-core'),
            internalApiKeyId: Env::string('INTERNAL_API_KEY_ID', 'local-key-1'),
            internalApiSecret: Env::string('INTERNAL_API_SECRET', 'change-me'),
            internalApiScopes: Env::csv('INTERNAL_API_SCOPES', InternalScope::values()),
        );
    }

    public function isDebugMode(): bool
    {
        return $this->debug && $this->environment === Environment::Local;
    }

    public function isProduction(): bool
    {
        return $this->environment === Environment::Production;
    }
}
