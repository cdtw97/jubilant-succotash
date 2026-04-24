<?php
declare(strict_types=1);

namespace MyFrancis\Core\Exceptions;

use MyFrancis\Core\Enums\HttpStatus;

class ForbiddenHttpException extends HttpException
{
    public function __construct(string $message = 'You do not have permission to access this resource.')
    {
        parent::__construct(HttpStatus::FORBIDDEN, $message);
    }
}
