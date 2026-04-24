<?php
declare(strict_types=1);

namespace Tests;

use MyFrancis\Core\Exceptions\ConfigurationException;
use MyFrancis\Core\Request;
use MyFrancis\Core\Response;
use Tests\Fixtures\Controllers\FixtureController;

final class RoutingTest extends FrameworkTestCase
{
    public function testGetRootRoutesToHomepage(): void
    {
        $application = $this->bootApplication();

        $response = $application->handle(new Request('GET', '/'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('This is the index title!', $response->body());
    }

    public function testGetAboutRoutesToAboutPage(): void
    {
        $application = $this->bootApplication();

        $response = $application->handle(new Request('GET', '/about'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('About', $response->body());
    }

    public function testUnknownPathReturnsNotFound(): void
    {
        $application = $this->bootApplication();

        $response = $application->handle(new Request('GET', '/missing-page'));

        self::assertSame(404, $response->statusCode());
    }

    public function testWrongMethodReturnsMethodNotAllowed(): void
    {
        $application = $this->bootApplication();

        $response = $application->handle(new Request('POST', '/about'));

        self::assertSame(405, $response->statusCode());
        self::assertSame('GET', $response->headers()['Allow'] ?? null);
    }

    public function testInheritedControllerMethodsAreNotRoutable(): void
    {
        $application = $this->bootApplication();
        $application->router()->get('/inherited', [FixtureController::class, 'inheritedAction']);

        $this->expectException(ConfigurationException::class);

        $application->router()->dispatch(new Request('GET', '/inherited'), $application->container());
    }

    public function testPathTraversalAttemptDoesNotLoadFiles(): void
    {
        $application = $this->bootApplication();

        $response = $application->handle(new Request('GET', '/../config/config.php'));

        self::assertSame(404, $response->statusCode());
    }

    public function testDynamicParamsRouteToControllerMethod(): void
    {
        $application = $this->bootApplication();
        $application->router()->get('/users/{id}', [FixtureController::class, 'user']);

        $response = $application->handle(new Request('GET', '/users/42'));

        self::assertSame(200, $response->statusCode());
        self::assertSame('user:42', $response->body());
    }

    public function testRegexConstraintsAreApplied(): void
    {
        $application = $this->bootApplication();
        $application->router()->get(
            '/orders/{code}',
            [FixtureController::class, 'regex'],
            constraints: ['code' => 'INV_[0-9]+'],
        );

        $matchingResponse = $application->handle(new Request('GET', '/orders/INV_123'));
        $nonMatchingResponse = $application->handle(new Request('GET', '/orders/BAD_123'));

        self::assertSame(200, $matchingResponse->statusCode());
        self::assertSame('code:INV_123', $matchingResponse->body());
        self::assertSame(404, $nonMatchingResponse->statusCode());
    }

    public function testRouteMiddlewareIsInvoked(): void
    {
        $application = $this->bootApplication();
        $middlewareInvoked = false;

        $application->router()->get(
            '/middleware-check',
            static fn (): Response => Response::html('ok'),
            middleware: [
                /**
                 * @param callable(Request): Response $next
                 */
                static function (Request $request, callable $next) use (&$middlewareInvoked): Response {
                    $middlewareInvoked = true;
                    $response = $next($request);

                    if (! $response instanceof Response) {
                        throw new \RuntimeException('Middleware pipeline must return a response.');
                    }

                    return $response;
                },
            ],
        );

        $response = $application->handle(new Request('GET', '/middleware-check'));

        self::assertSame(200, $response->statusCode());
        self::assertTrue($middlewareInvoked);
    }
}
