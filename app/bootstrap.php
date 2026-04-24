<?php
declare(strict_types=1);

use MyFrancis\Config\AppConfig;
use MyFrancis\Config\DatabaseConfig;
use MyFrancis\Core\Application;
use MyFrancis\Core\Container;
use MyFrancis\Security\CsrfTokenManager;
use MyFrancis\Security\Escaper;
use MyFrancis\Support\Env;

if (! function_exists('e')) {
    function e(mixed $value): string
    {
        static $escaper = null;

        if (! $escaper instanceof Escaper) {
            $escaper = new Escaper();
        }

        return $escaper->html($value);
    }
}

if (! function_exists('base_path_url')) {
    function base_path_url(): string
    {
        $scriptName = server_string_value($_SERVER, 'SCRIPT_NAME', '/index.php');
        $directory = str_replace('\\', '/', dirname($scriptName));

        if ($directory === '/' || $directory === '.' || $directory === '\\') {
            return '';
        }

        return rtrim($directory, '/');
    }
}

if (! function_exists('app_url')) {
    function app_url(string $path = ''): string
    {
        $basePath = base_path_url();

        if ($path === '') {
            return $basePath !== '' ? $basePath : '/';
        }

        return ($basePath !== '' ? $basePath : '') . '/' . ltrim($path, '/');
    }
}

if (! function_exists('asset')) {
    function asset(string $path = ''): string
    {
        return app_url($path);
    }
}

if (! function_exists('route')) {
    /**
     * @param array<string, bool|float|int|string|\Stringable|null> $parameters
     */
    function route(string $name, array $parameters = []): string
    {
        $application = Application::getInstance();

        if (! $application instanceof Application) {
            return app_url();
        }

        return app_url(ltrim($application->router()->url($name, $parameters), '/'));
    }
}

if (! function_exists('csrf_token')) {
    function csrf_token(): string
    {
        $csrfTokenManager = application_csrf_manager();

        return $csrfTokenManager instanceof CsrfTokenManager
            ? $csrfTokenManager->generateToken()
            : '';
    }
}

if (! function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $csrfTokenManager = application_csrf_manager();

        return $csrfTokenManager instanceof CsrfTokenManager
            ? $csrfTokenManager->field()
            : '';
    }
}

if (! function_exists('application_csrf_manager')) {
    function application_csrf_manager(): ?CsrfTokenManager
    {
        $application = Application::getInstance();

        if (! $application instanceof Application) {
            return null;
        }

        return $application->container()->get(CsrfTokenManager::class);
    }
}

if (! function_exists('server_string_value')) {
    /**
     * @param array<int|string, mixed> $source
     */
    function server_string_value(array $source, string $key, string $default = ''): string
    {
        $value = $source[$key] ?? $default;

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $default;
    }
}

$basePath = dirname(__DIR__);

Env::load(
    $basePath . DIRECTORY_SEPARATOR . '.env',
    $basePath . DIRECTORY_SEPARATOR . '.env.example',
);

$container = new Container();

return new Application(
    $basePath,
    $container,
    AppConfig::fromEnv(),
    DatabaseConfig::fromEnv(),
);
