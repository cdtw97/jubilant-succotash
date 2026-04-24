<?php
declare(strict_types=1);

namespace MyFrancis\Core\Exceptions;

use MyFrancis\Core\Enums\HttpStatus;

final class UnprocessableEntityHttpException extends HttpException
{
    public function __construct(string $message = 'The request payload could not be validated.')
    {
        parent::__construct(HttpStatus::UNPROCESSABLE_ENTITY, $message);
    }
}
