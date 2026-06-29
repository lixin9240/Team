<?php

namespace App\Exceptions;

use App\Enums\ResponseCode;
use RuntimeException;

/**
 * 业务异常
 *
 * Service 层主动抛出此异常，由 Handler 统一处理返回
 */
class BusinessException extends RuntimeException
{
    public readonly ResponseCode $codeEnum;

    public function __construct(
        string $message = '业务处理失败',
        ?ResponseCode $codeEnum = null
    ) {
        parent::__construct($message);
        $this->codeEnum = $codeEnum ?? ResponseCode::BUSINESS_ERROR;
    }
}
