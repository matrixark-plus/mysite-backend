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

use App\Repository\BlogRepository;
use Hyperf\Di\Annotation\Inject;
use InvalidArgumentException;
use Throwable;

/**
 * 异步更新博客阅读量任务
 * 用于异步处理博客阅读量的数据库更新，减轻实时压力.
 */
class AsyncUpdateBlogViewTask extends AbstractTask
{
    /**
     * @Inject
     * @var BlogRepository
     */
    protected $blogRepository;

    /**
     * 处理异步更新博客阅读量任务
     */
    protected function handle(): bool
    {
        // 解析任务数据
        $blogId = $this->data['blog_id'] ?? 0;

        // 验证必要参数
        if (empty($blogId) || ! is_numeric($blogId)) {
            throw new InvalidArgumentException('博客ID不能为空');
        }

        try {
            // 更新数据库中的阅读量
            $this->blogRepository->incrementViewCount((int) $blogId);

            $this->logger->info('异步更新博客阅读量成功', ['blog_id' => $blogId]);
            return true;
        } catch (Throwable $e) {
            $this->logger->error('异步更新博客阅读量失败', [
                'blog_id' => $blogId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * 错误处理 - 实现重试机制.
     */
    protected function handleError(Throwable $e): void
    {
        // 获取重试次数
        $retryCount = $this->data['retry_count'] ?? 0;
        $maxRetries = $this->data['max_retries'] ?? 3;

        // 如果还可以重试
        if ($retryCount < $maxRetries) {
            // 增加重试次数
            $this->data['retry_count'] = $retryCount + 1;

            // 这里可以实现延时重试策略
            // 由于阅读量更新不是关键业务，可以使用简单的重试策略
            $this->logger->info('准备重试异步更新博客阅读量', [
                'blog_id' => $this->data['blog_id'] ?? 0,
                'retry_count' => $this->data['retry_count'],
                'max_retries' => $maxRetries,
            ]);

            // 注意：这里只是记录日志，实际重试需要在TaskService中实现
        }
    }

    /**
     * 脱敏敏感数据.
     *
     * @param array $data 原始数据
     * @return array 脱敏后的数据
     */
    protected function maskSensitiveData(array $data): array
    {
        // 这个任务没有敏感数据需要脱敏
        return $data;
    }
}
