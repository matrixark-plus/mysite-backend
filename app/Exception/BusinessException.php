<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Exception;

use Exception;
use Throwable;

/**
 * 业务异常类
 * 用于表示可预测的业务错误情况.
 */
class BusinessException extends Exception
{
    /**
     * @var int HTTP状态码
     */
    protected $statusCode = 400;

    /**
     * @var string 错误码
     */
    protected $errorCode = 'BUSINESS_ERROR';

    /**
     * BusinessException constructor.
     * @param string $message 错误信息
     * @param string $errorCode 错误码
     * @param int $statusCode HTTP状态码
     * @param null|Throwable $previous 前一个异常
     */
    public function __construct(
        string $message = '业务异常',
        string $errorCode = 'BUSINESS_ERROR',
        int $statusCode = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
    }

    /**
     * 获取HTTP状态码
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * 获取错误码
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
