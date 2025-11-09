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

namespace App\Task;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * 异步日志任务
 * 用于异步处理各种日志记录
 */
class AsyncLogTask extends AbstractTask
{
    /**
     * @Inject
     * @var LoggerFactory
     */
    protected $loggerFactory;

    /**
     * 处理异步日志任务
     *
     * @return bool
     */
    protected function handle(): bool
    {
        // 解析任务数据
        $level = $this->data['level'] ?? 'info';
        $message = $this->data['message'] ?? '';
        $context = $this->data['context'] ?? [];
        $channel = $this->data['channel'] ?? 'default';

        // 验证必要参数
        if (empty($message)) {
            throw new \InvalidArgumentException('日志消息不能为空');
        }

        // 验证日志级别
        $validLevels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
        if (! in_array(strtolower($level), $validLevels)) {
            $level = 'info';
        }

        try {
            // 获取指定通道的logger实例
            $logger = $this->loggerFactory->get($channel);

            // 写入日志
            $logger->{$level}($message, $context);

            return true;
        } catch (\Throwable $e) {
            // 如果写入到指定通道失败，尝试写入到error通道
            try {
                $errorLogger = $this->loggerFactory->get('error');
                $errorLogger->error('异步日志写入失败', [
                    'original_level' => $level,
                    'original_message' => $message,
                    'original_channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable $innerE) {
                // 如果所有日志写入都失败，至少在控制台记录一下
                echo sprintf('[ERROR] Async log writing failed: %s\n', $e->getMessage());
            }

            return false;
        }
    }

    /**
     * 错误处理 - 实现重试机制
     *
     * @param \Throwable $e
     */
    protected function handleError(\Throwable $e): void
    {
        // 获取重试次数
        $retryCount = $this->data['retry_count'] ?? 0;
        $maxRetries = $this->data['max_retries'] ?? 3;

        // 如果还可以重试
        if ($retryCount < $maxRetries) {
            // 增加重试次数
            $this->data['retry_count'] = $retryCount + 1;

            // 这里可以实现延时重试策略
            // 例如使用指数退避算法计算下次重试时间
            $delay = $this->calculateRetryDelay($retryCount);

            // 记录重试信息
            $this->logger->warning('异步日志任务将重试', [
                'current_retry' => $retryCount + 1,
                'max_retries' => $maxRetries,
                'delay_ms' => $delay,
                'message' => $this->data['message'] ?? '',
            ]);

            // 在实际应用中，这里会将任务重新放入队列
            // 由于这只是一个演示，我们只记录重试信息
        }
    }

    /**
     * 计算重试延迟时间
     * 使用指数退避算法
     *
     * @param int $retryCount 当前重试次数
     * @return int 延迟时间（毫秒）
     */
    private function calculateRetryDelay(int $retryCount): int
    {
        // 基础延迟时间：100ms
        $baseDelay = 100;
        // 最大延迟时间：5s
        $maxDelay = 5000;

        // 指数退避：baseDelay * (2^retryCount) + 随机抖动
        $delay = $baseDelay * pow(2, $retryCount);
        // 添加随机抖动（±10%）
        $jitter = $delay * 0.1;
        $delay += random_int(-$jitter, $jitter);

        // 确保不超过最大延迟时间
        return min($delay, $maxDelay);
    }
}