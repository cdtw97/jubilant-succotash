<?php
declare(strict_types=1);

namespace MyFrancis\Core\Exceptions;

use MyFrancis\Core\Enums\HttpStatus;

final class UnsupportedMediaTypeHttpException extends HttpException
{
    public function __construct(string $message = 'The request content type is not supported.')
    {
        parent::__construct(HttpStatus::UNSUPPORTED_MEDIA_TYPE, $message);
    }
}
