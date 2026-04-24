<?php
declare(strict_types=1);

namespace MyFrancis\Core\Exceptions;

use MyFrancis\Core\Enums\HttpStatus;

final class NotAcceptableHttpException extends HttpException
{
    public function __construct(string $message = 'The requested response format is not available.')
    {
        parent::__construct(HttpStatus::NOT_ACCEPTABLE, $message);
    }
}
