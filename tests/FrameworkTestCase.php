<?php
declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use MyFrancis\Core\Application;
use MyFrancis\Core\ErrorHandler;
use MyFrancis\Core\Request;
use MyFrancis\Security\InternalApi\HmacSigner;
use MyFrancis\Security\InternalApi\NonceStore;
use MyFrancis\Support\Env;
use MyFrancis\Support\Logger;
use PHPUnit\Framework\TestCase;

abstract class FrameworkTestCase extends TestCase
{
    protected string $basePath;
    protected ?Application $application = null;
    private bool $registeredFrameworkErrorHandler = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = dirname(__DIR__);
        $this->resetGlobals();
        $this->clearRuntimeArtifacts();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_unset();
            session_destroy();
        }

        if ($this->registeredFrameworkErrorHandler) {
            restore_error_handler();
        }

        ErrorHandler::reset();
        Env::reset();
        $this->clearRuntimeArtifacts();
        $this->resetGlobals();
        $this->application = null;
        $this->registeredFrameworkErrorHandler = false;

        parent::tearDown();
    }

    /**
     * @param array<string, scalar> $serverOverrides
     */
    protected function bootApplication(array $serverOverrides = []): Application
    {
        $this->resetGlobals($serverOverrides);

        /** @var Application $application */
        $application = require $this->basePath . '/app/bootstrap.php';

        $this->application = $application
            ->loadRoutes($this->basePath . '/routes/web.php')
            ->loadRoutes($this->basePath . '/routes/internal.php');
        $this->registeredFrameworkErrorHandler = true;

        $this->service(NonceStore::class)->clear();

        $logFile = $this->service(Logger::class)->logFile();

        if (is_file($logFile)) {
            unlink($logFile);
        }

        return $this->application;
    }

    /**
     * @template TObject of object
     *
     * @param class-string<TObject> $id
     * @return TObject
     */
    protected function service(string $id): object
    {
        self::assertInstanceOf(Application::class, $this->application);

        $service = $this->application->container()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }

    /**
     * @param array<int|string, mixed> $payload
     * @param array<string, string> $extraHeaders
     * @param array<int|string, mixed> $queryParameters
     * @param array<string, scalar> $serverParameters
     */
    protected function signedInternalRequest(
        Application $application,
        string $method,
        string $path,
        array $payload = [],
        array $extraHeaders = [],
        ?DateTimeImmutable $timestamp = null,
        ?string $nonce = null,
        ?string $rawBody = null,
        ?string $signingBody = null,
        array $queryParameters = [],
        array $serverParameters = [],
    ): Request {
        $rawBody ??= $method === 'GET'
            ? ''
            : (string) json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signingBody ??= $rawBody;

        $baseRequest = new Request(
            $method,
            $path,
            queryParameters: $queryParameters,
            rawBody: $signingBody,
            serverParameters: ['SCRIPT_NAME' => '/index.php', ...$serverParameters],
        );

        $signer = $application->container()->get(HmacSigner::class);
        $config = $application->appConfig();
        $headers = $signer->buildSignedHeaders(
            $baseRequest,
            $config->internalApiAppId,
            $config->internalApiKeyId,
            $config->internalApiSecret,
            $timestamp,
            $nonce,
        );

        return new Request(
            $method,
            $path,
            queryParameters: $queryParameters,
            headers: [...$headers, ...$extraHeaders],
            rawBody: $rawBody,
            serverParameters: ['SCRIPT_NAME' => '/index.php', ...$serverParameters],
        );
    }

    /**
     * @param array<int|string, mixed> $queryParameters
     * @param array<string, scalar> $serverParameters
     * @return array<string, string>
     */
    protected function signedInternalHeaders(
        Application $application,
        string $method,
        string $path,
        string $rawBody = '',
        ?DateTimeImmutable $timestamp = null,
        ?string $nonce = null,
        array $queryParameters = [],
        array $serverParameters = [],
    ): array {
        $request = new Request(
            $method,
            $path,
            queryParameters: $queryParameters,
            rawBody: $rawBody,
            serverParameters: ['SCRIPT_NAME' => '/index.php', ...$serverParameters],
        );
        $signer = $application->container()->get(HmacSigner::class);
        $config = $application->appConfig();

        return $signer->buildSignedHeaders(
            $request,
            $config->internalApiAppId,
            $config->internalApiKeyId,
            $config->internalApiSecret,
            $timestamp,
            $nonce,
        );
    }

    protected function logContents(): string
    {
        if ($this->application === null) {
            return '';
        }

        $logFile = $this->service(Logger::class)->logFile();

        return is_file($logFile) ? (string) file_get_contents($logFile) : '';
    }

    /**
     * @param array<string, scalar> $serverOverrides
     */
    private function resetGlobals(array $serverOverrides = []): void
    {
        $_SERVER = ['SCRIPT_NAME' => '/index.php', ...$serverOverrides];
        $_ENV = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];
        $_SESSION = [];
    }

    private function clearRuntimeArtifacts(): void
    {
        $paths = [
            $this->basePath . '/storage/logs/app.log',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        foreach (glob($this->basePath . '/storage/framework/nonces/*.json') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        foreach (glob($this->basePath . '/storage/framework/sessions/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
