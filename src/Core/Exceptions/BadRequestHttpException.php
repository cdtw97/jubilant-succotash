<?php
declare(strict_types=1);

namespace MyFrancis\Core\Exceptions;

use MyFrancis\Core\Enums\HttpStatus;

final class BadRequestHttpException extends HttpException
{
    public function __construct(string $message = 'The request could not be understood by the server.')
    {
        parent::__construct(HttpStatus::BAD_REQUEST, $message);
    }
}
