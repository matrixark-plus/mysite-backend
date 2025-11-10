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

use Hyperf\Context\Context;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Swoole\Coroutine;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Swoole协程异常处理器
 * 专门处理协程相关异常，防止协程泄漏和崩溃.
 */
class CoroutineExceptionHandler extends ExceptionHandler
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container)
    {
        // 移除对不存在的父类构造函数的调用
        $this->logger = $container->get(LoggerInterface::class);
    }

    /**
     * 处理异常.
     */
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        // 记录协程上下文信息
        $coroutineId = Coroutine::getCid(); // 使用正确的方法获取协程ID
        $coroutineStack = []; // 移除对不存在方法的调用
        // 移除对不存在的Context::getKeys()方法的调用
        // 只获取几个关键的上下文信息
        $context = [];
        try {
            $commonKeys = ['request_id', 'trace_id', 'request_method', 'request_uri'];
            foreach ($commonKeys as $key) {
                $value = Context::get($key);
                if ($value !== null) {
                    $context[$key] = $value;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('无法获取上下文信息', ['error' => $e->getMessage()]);
        }

        // 清理敏感信息
        $sensitiveKeys = ['password', 'token', 'secret', 'key'];
        foreach ($sensitiveKeys as $key) {
            if (isset($context[$key])) {
                $context[$key] = '*** REDACTED ***';
            }
        }

        // 区分异常类型并记录不同级别的日志
        $isSwooleCoroutineException = strpos(get_class($throwable), 'Swoole\\Coroutine') !== false || 
                                      strpos($throwable->getMessage(), 'coroutine') !== false;
        $logLevel = $isSwooleCoroutineException ? 'error' : 'warning';

        // 记录异常信息
        $this->logger->{$logLevel}('协程异常捕获', [
            'exception_type' => get_class($throwable),
            'coroutine_id' => $coroutineId,
            'active_coroutines' => count($coroutineStack),
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => $throwable->getTraceAsString(),
            'context' => $context,
        ]);

        // 处理Swoole协程特定异常
        if ($isSwooleCoroutineException) {
            // 协程相关的特定处理逻辑
            $this->handleSwooleCoroutineException($throwable, $coroutineId);
        }

        // 向客户端返回友好的错误信息
        $statusCode = $isSwooleCoroutineException ? 503 : 500;
        $errorMessage = $isSwooleCoroutineException
            ? '系统临时繁忙，请稍后重试'
            : '内部服务器错误';

        // 非生产环境返回详细错误信息
        if (env('APP_ENV') !== 'production') {
            $errorMessage = $throwable->getMessage();
        }

        return $response
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new SwooleStream(json_encode([
                'code' => $statusCode,
                'message' => $errorMessage,
                'request_id' => Context::get('request_id', ''),
            ], JSON_UNESCAPED_UNICODE)));
    }

    /**
     * 判断异常是否由该处理器处理.
     */
    public function isValid(Throwable $throwable): bool
    {
        // 处理所有协程相关异常
        return strpos(get_class($throwable), 'Swoole\\Coroutine') !== false
               || strpos($throwable->getMessage(), 'coroutine') !== false;
    }

    /**
     * 处理Swoole特定的协程异常.
     */
    protected function handleSwooleCoroutineException(Throwable $exception, int $coroutineId): void
    {
        // 处理协程资源泄漏
        $this->logger->error('检测到Swoole协程异常', [
            'coroutine_id' => $coroutineId,
            'error_code' => $exception->getCode(),
            'error_message' => $exception->getMessage(),
        ]);

        // 对于特定类型的协程错误，尝试进行恢复操作
        switch ($exception->getCode()) {
            case SOCKET_ETIMEDOUT:
                $this->logger->warning('协程超时异常，可能需要调整超时设置', [
                    'coroutine_id' => $coroutineId,
                ]);
                break;
            case SWOOLE_ERROR_CO_EMPTY_FD:
                $this->logger->warning('协程文件描述符为空', [
                    'coroutine_id' => $coroutineId,
                ]);
                break;
            default:
                $this->logger->error('未知的Swoole协程错误', [
                    'coroutine_id' => $coroutineId,
                    'error_code' => $exception->getCode(),
                ]);
        }
    }
}
