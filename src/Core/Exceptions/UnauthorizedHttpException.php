<?php
declare(strict_types=1);

namespace MyFrancis\Core\Exceptions;

use MyFrancis\Core\Enums\HttpStatus;

final class UnauthorizedHttpException extends HttpException
{
    public function __construct(string $message = 'Authentication is required to access this resource.')
    {
        parent::__construct(HttpStatus::UNAUTHORIZED, $message);
    }
}
