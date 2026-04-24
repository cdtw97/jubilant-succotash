<?php
declare(strict_types=1);

namespace Tests;

use MyFrancis\Support\Logger;

final class LoggerTest extends FrameworkTestCase
{
    public function testLogsIncludeRequestIdAndRedactSecrets(): void
    {
        $this->bootApplication();
        $logger = $this->service(Logger::class);

        $logger->info('Internal API request received.', [
            'request_id' => 'req_test_12345',
            'password' => 'super-secret',
            'token' => 'token-value',
            'signature' => 'signature-value',
            'cookie' => 'mf_session=abc',
            'Authorization' => 'Bearer abc123',
            'db_password' => 'root-password',
            'INTERNAL_API_SECRET' => 'change-me',
            'X-MF-Signature' => 'sig',
        ]);

        $logFile = $logger->logFile();
        $contents = $this->logContents();

        self::assertFileExists($logFile);
        self::assertStringContainsString('"request_id":"req_test_12345"', $contents);
        self::assertStringContainsString('[REDACTED]', $contents);
        self::assertStringNotContainsString('super-secret', $contents);
        self::assertStringNotContainsString('token-value', $contents);
        self::assertStringNotContainsString('signature-value', $contents);
        self::assertStringNotContainsString('mf_session=abc', $contents);
        self::assertStringNotContainsString('Bearer abc123', $contents);
        self::assertStringContainsString('storage/logs/app.log', str_replace('\\', '/', $logFile));
        self::assertStringNotContainsString('/public/', str_replace('\\', '/', $logFile));
    }
}
