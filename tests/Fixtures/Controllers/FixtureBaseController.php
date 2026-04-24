<?php
declare(strict_types=1);

namespace Tests\Fixtures\Controllers;

use MyFrancis\Core\Controller;
use MyFrancis\Core\Response;
use MyFrancis\Core\View;

abstract class FixtureBaseController extends Controller
{
    public function __construct(View $view)
    {
        parent::__construct($view);
    }

    public function inheritedAction(): Response
    {
        return Response::html('base-action');
    }
}
