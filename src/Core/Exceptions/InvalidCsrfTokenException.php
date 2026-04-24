<?php
declare(strict_types=1);

namespace MyFrancis\Core\Exceptions;

final class InvalidCsrfTokenException extends ForbiddenHttpException
{
    public function __construct()
    {
        parent::__construct('Invalid CSRF token.');
    }
}
