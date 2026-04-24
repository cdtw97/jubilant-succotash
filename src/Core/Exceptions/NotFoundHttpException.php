<?php
declare(strict_types=1);

namespace MyFrancis\Core\Exceptions;

use MyFrancis\Core\Enums\HttpStatus;

final class NotFoundHttpException extends HttpException
{
    public function __construct(string $message = 'The requested resource was not found.')
    {
        parent::__construct(HttpStatus::NOT_FOUND, $message);
    }
}
