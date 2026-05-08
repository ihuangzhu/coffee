<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use RuntimeException;

/**
 * 业务异常基类。
 *
 * 三元组：errorCode（机器友好的错误码）+ httpStatus + details；
 * bootstrap/app.php 中 withExceptions 可统一渲染为 JSON 响应。
 */
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
