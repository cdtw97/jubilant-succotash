<?php
declare(strict_types=1);

namespace MyFrancis\Http\Middleware;

use MyFrancis\Core\Request;
use MyFrancis\Core\Response;

interface MiddlewareInterface
{
    /**
     * @param callable(Request): Response $next
     */
    public function process(Request $request, callable $next): Response;
}
