<?php

declare(strict_types=1);
/**
 * 日志处理Trait
 * 提供统一的日志记录功能，封装日志处理逻辑
 */
namespace App\Traits;

use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Support\env;
use Psr\Log\LoggerInterface;

/**
 * @property LoggerInterface $logger
 */
trait LogTrait
{
    /**
     * 获取日志实例
     * @param string $name 日志通道名称
     * @return LoggerInterface
     */
    protected function getLogger(string $name = 'app'): LoggerInterface
    {
        // 如果当前对象已经有logger属性，则直接返回
        if (property_exists($this, 'logger') && $this->logger instanceof LoggerInterface) {
            return $this->logger;
        }
        
        // 从容器中获取
        $container = \Hyperf\Context\ApplicationContext::getContainer();
        $loggerFactory = $container->get(LoggerFactory::class);
        return $loggerFactory->get($name);
    }
    
    /**
     * 记录操作日志
     * @param string $action 操作名称
     * @param array $context 上下文信息
     * @param string $channel 日志通道
     */
    protected function logAction(string $action, array $context = [], string $channel = 'app'): void
    {
        try {
            $logger = $this->getLogger($channel);
            $logger->info($action, $context);
        } catch (\Throwable $e) {
            // 日志记录失败时降级到系统日志
            error_log(sprintf('日志记录失败: %s, 上下文: %s', $action, json_encode($context)));
        }
    }
    
    /**
     * 记录错误日志
     * @param string $error 错误信息
     * @param array $context 上下文信息
     * @param \Throwable|null $exception 异常对象
     * @param string $channel 日志通道
     */
    protected function logError(string $error, array $context = [], ?\Throwable $exception = null, string $channel = 'app'): void
    {
        try {
            $logger = $this->getLogger($channel);
            
            // 如果有异常对象，添加异常信息到上下文
            if ($exception) {
                $context = array_merge($context, [
                    'message' => $exception->getMessage(),
                    'exception' => get_class($exception),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                ]);
            }
            
            $logger->error($error, $context);
        } catch (\Throwable $e) {
            // 日志记录失败时降级到系统日志
            $errorInfo = sprintf('错误日志记录失败: %s', $error);
            if ($exception) {
                $errorInfo .= sprintf(', 异常: %s', $exception->getMessage());
            }
            error_log($errorInfo);
        }
    }
    
    /**
     * 记录警告日志
     * @param string $warning 警告信息
     * @param array $context 上下文信息
     * @param string $channel 日志通道
     */
    protected function logWarning(string $warning, array $context = [], string $channel = 'app'): void
    {
        try {
            $logger = $this->getLogger($channel);
            $logger->warning($warning, $context);
        } catch (\Throwable $e) {
            // 日志记录失败时降级到系统日志
            error_log(sprintf('警告日志记录失败: %s, 上下文: %s', $warning, json_encode($context)));
        }
    }
    
    /**
     * 记录调试日志
     * @param string $debug 调试信息
     * @param array $context 上下文信息
     * @param string $channel 日志通道
     */
    protected function logDebug(string $debug, array $context = [], string $channel = 'app'): void
    {
        try {
            $logger = $this->getLogger($channel);
            $logger->debug($debug, $context);
        } catch (\Throwable $e) {
            // 调试日志通常不降级，避免生产环境输出过多日志
            // 仅在开发环境记录系统日志
            if (env('APP_ENV') === 'development' || env('APP_ENV') === 'dev') {
                error_log(sprintf('调试日志记录失败: %s', $debug));
            }
        }
    }
}