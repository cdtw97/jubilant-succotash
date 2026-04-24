<?php
declare(strict_types=1);

namespace Tests;

use MyFrancis\Core\Request;
use RuntimeException;

final class ErrorHandlingTest extends FrameworkTestCase
{
    public function testProductionStyleHtmlErrorsAreGenericAndSanitized(): void
    {
        $application = $this->bootApplication(['APP_DEBUG' => 'false', 'APP_ENV' => 'production']);
        $application->router()->get('/boom', static function (): never {
            throw new RuntimeException('DB_PASSWORD=secret C:\\sensitive\\path token=abc Authorization: Bearer secret');
        }, middleware: ['request.id', 'security.headers']);

        $response = $application->handle(new Request('GET', '/boom'));

        self::assertSame(500, $response->statusCode());
        self::assertStringContainsString('An internal server error occurred.', $response->body());
        self::assertStringContainsString('Request ID:', $response->body());
        self::assertStringNotContainsString('secret', $response->body());
        self::assertStringNotContainsString('C:\\sensitive\\path', $response->body());
        self::assertStringNotContainsString('Authorization', $response->body());
    }

    public function testProductionStyleJsonErrorsUseSanitizedEnvelope(): void
    {
        $application = $this->bootApplication(['APP_DEBUG' => 'false', 'APP_ENV' => 'production']);
        $application->router()->get('/boom-json', static function (): never {
            throw new RuntimeException('cookie=secret signature=abc token=xyz');
        }, middleware: ['request.id', 'security.headers']);

        $response = $application->handle(new Request('GET', '/boom-json', headers: ['Accept' => 'application/json']));

        self::assertSame(500, $response->statusCode());
        self::assertStringContainsString('"error"', $response->body());
        self::assertStringContainsString('"internal_server_error"', $response->body());
        self::assertStringContainsString('"request_id"', $response->body());
        self::assertStringNotContainsString('cookie=secret', $response->body());
        self::assertStringNotContainsString('signature=abc', $response->body());
    }

    public function testDebugLocalMayShowClassButStillRedactsSecrets(): void
    {
        $application = $this->bootApplication(['APP_DEBUG' => 'true', 'APP_ENV' => 'local']);
        $application->router()->get('/boom-debug', static function (): never {
            throw new RuntimeException('INTERNAL_API_SECRET=change-me token=abc123');
        }, middleware: ['request.id', 'security.headers']);

        $response = $application->handle(new Request('GET', '/boom-debug', headers: ['Accept' => 'application/json']));

        self::assertSame(500, $response->statusCode());
        self::assertStringContainsString('RuntimeException', $response->body());
        self::assertStringNotContainsString('change-me', $response->body());
        self::assertStringNotContainsString('token=abc123', $response->body());
    }
}
