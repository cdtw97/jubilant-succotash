<?php
declare(strict_types=1);

namespace MyFrancis\Core\Exceptions;

use Exception;
use MyFrancis\Core\Enums\HttpStatus;

class HttpException extends Exception
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly HttpStatus $status,
        string $message,
        private readonly array $headers = [],
    ) {
        parent::__construct($message, $status->value);
    }

    public function status(): HttpStatus
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }
}
