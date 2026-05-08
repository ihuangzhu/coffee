<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use RuntimeException;

class BusinessException extends RuntimeException
{
    public function __construct(
        protected string $errorCode,
        string $message = '',
        protected int $httpStatus = 400,
        protected array $details = [],
    ) {
        parent::__construct($message ?: $errorCode);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function details(): array
    {
        return $this->details;
    }
}
