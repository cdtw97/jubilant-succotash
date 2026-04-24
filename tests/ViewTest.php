<?php
declare(strict_types=1);

namespace Tests;

use MyFrancis\Core\Exceptions\ViewException;
use MyFrancis\Core\View;

final class ViewTest extends FrameworkTestCase
{
    public function testEscaperHelperEscapesHtmlSpecialCharacters(): void
    {
        $this->bootApplication();

        self::assertSame('&lt;&gt;&amp;&#039;&quot;', e('<>&\'"'));
    }

    public function testViewRejectsPathTraversalSequences(): void
    {
        $this->bootApplication();
        $view = $this->service(View::class);

        $invalidViews = [
            '../config/config',
            '/pages/index',
            'pages\\index',
            "pages\0index",
        ];
        $rejectedCount = 0;

        foreach ($invalidViews as $invalidView) {
            try {
                $view->render($invalidView);
                self::fail('Expected invalid view name to be rejected.');
            } catch (ViewException) {
                $rejectedCount++;
            }
        }

        self::assertSame(count($invalidViews), $rejectedCount);
    }

    public function testHomepageRendersEscapedTitle(): void
    {
        $this->bootApplication();
        $view = $this->service(View::class);

        $output = $view->render('pages.index', [
            'title' => '<script>alert("x")</script>',
            'text' => 'Safe body text.',
        ]);

        self::assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $output);
        self::assertStringNotContainsString('<script>alert("x")</script>', $output);
    }

    public function testHomepagePreservesPhaseOneContent(): void
    {
        $application = $this->bootApplication();

        $response = $application->handle(new \MyFrancis\Core\Request('GET', '/'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('This is the index title!', $response->body());
        self::assertStringContainsString('This is the CDTW MVC PHP Framework please refer to the docs.', $response->body());
    }
}
