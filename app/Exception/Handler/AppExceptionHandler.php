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

namespace App\Exception\Handler;

use App\Constants\ResponseMessage;
use App\Constants\StatusCode;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

use function Hyperf\Support\env;

class AppExceptionHandler extends ExceptionHandler
{
    public function __construct(protected StdoutLoggerInterface $logger) {}

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        // 获取请求信息（如果可用）
        $request = ApplicationContext::getContainer()->get(ServerRequestInterface::class) ?? null;
        $uri = $request ? (string) $request->getUri() : 'unknown';
        $method = $request ? $request->getMethod() : 'unknown';
        $clientIp = $request && $request->getServerParams() ? $request->getServerParams()['remote_addr'] ?? 'unknown' : 'unknown';

        // 构建统一格式的日志消息
        $logMessage = sprintf(
            'API Error: %s %s - IP: %s - %s: %s',
            $method,
            $uri,
            $clientIp,
            get_class($throwable),
            $throwable->getMessage()
        );

        // 根据异常类型确定日志级别
        $this->logException($throwable, $logMessage);

        // 获取错误码，优先使用异常的code，验证状态码有效性
        $code = $this->validateStatusCode($throwable->getCode() ?: StatusCode::INTERNAL_SERVER_ERROR);

        // 构建JSON格式的错误响应
        $errorResponse = [
            'code' => $code,
            'message' => $this->getErrorMessage($throwable),
            'data' => null,
        ];

        // 将数组转换为JSON字符串
        $jsonResponse = json_encode($errorResponse, JSON_UNESCAPED_UNICODE);

        // 返回JSON响应
        return $response
            ->withHeader('Server', 'Hyperf')
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($code)
            ->withBody(new SwooleStream($jsonResponse));
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }

    /**
     * 获取错误消息
     * 根据不同的环境返回适当的错误信息.
     */
    private function getErrorMessage(Throwable $throwable): string
    {
        // 使用统一的环境判断逻辑
        if ($this->isDevelopmentEnvironment()) {
            return $throwable->getMessage();
        }

        // 对于评论长度限制等特定异常，可以返回具体错误
        if (strpos($throwable->getMessage(), '评论内容不能超过') !== false) {
            return $throwable->getMessage();
        }

        return ResponseMessage::SERVER_ERROR;
    }

    /**
     * 判断是否为开发环境
     * 环境判断逻辑.
     * @return bool 是否为开发环境
     */
    private function isDevelopmentEnvironment(): bool
    {
        $env = env('APP_ENV', 'production');
        return in_array($env, ['dev', 'development', 'local', 'test']);
    }

    /**
     * 根据异常类型记录日志.
     */
    private function logException(Throwable $exception, string $logMessage): void
    {
        // 异常类型判断
        $className = get_class($exception);

        // 404、401、403错误记录为warning级别
        if (strpos($className, 'NotFoundHttpException') !== false
            || strpos($className, 'UnauthorizedHttpException') !== false
            || strpos($className, 'ForbiddenHttpException') !== false) {
            $this->logger->warning($logMessage, ['exception' => $exception]);
        }
        // 400、422错误记录为info级别
        elseif (strpos($className, 'BadRequestHttpException') !== false
                || strpos($className, 'ValidationException') !== false) {
            $this->logger->info($logMessage, ['exception' => $exception]);
        }
        // 其他错误记录为error级别
        else {
            $this->logger->error($logMessage, ['exception' => $exception]);
        }
    }

    /**
     * 验证HTTP状态码的有效性.
     */
    private function validateStatusCode(int $statusCode): int
    {
        if ($statusCode >= 100 && $statusCode < 600) {
            return $statusCode;
        }
        return StatusCode::INTERNAL_SERVER_ERROR;
    }
}
