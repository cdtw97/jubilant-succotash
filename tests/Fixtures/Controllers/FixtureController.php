<?php
declare(strict_types=1);

namespace Tests\Fixtures\Controllers;

use MyFrancis\Core\Response;

final class FixtureController extends FixtureBaseController
{
    public function user(int $id): Response
    {
        return Response::html('user:' . $id);
    }

    public function regex(string $code): Response
    {
        return Response::html('code:' . $code);
    }
}
