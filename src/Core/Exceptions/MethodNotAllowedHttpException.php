<?php
declare(strict_types=1);

namespace MyFrancis\Core\Exceptions;

use MyFrancis\Core\Enums\HttpStatus;

final class MethodNotAllowedHttpException extends HttpException
{
    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(array $allowedMethods)
    {
        $methods = [];

        foreach ($allowedMethods as $allowedMethod) {
            $normalizedMethod = strtoupper(trim($allowedMethod));

            if ($normalizedMethod === '') {
                continue;
            }

            $methods[$normalizedMethod] = true;
        }

        parent::__construct(
            HttpStatus::METHOD_NOT_ALLOWED,
            'The requested HTTP method is not allowed for this route.',
            ['Allow' => implode(', ', array_keys($methods))],
        );
    }
}
