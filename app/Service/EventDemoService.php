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

namespace App\Service;

use App\Event\DataUpdatedEvent;
use App\Event\NewCommentEvent;
use App\Task\AsyncLogTask;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Event\Contract\EventDispatcherInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * 事件驱动架构示例服务
 * 演示如何使用事件系统和异步任务确保最终一致性
 */
class EventDemoService
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @Inject
     * @var TaskService
     */
    protected $taskService;

    /**
     * @Inject
     * @var RedisLockService
     */
    protected $redisLockService;

    /**
     * 演示创建实体并触发数据更新事件
     *
     * @param string $entityType 实体类型
     * @param array $entityData 实体数据
     * @return array 操作结果
     */
    public function createEntity(string $entityType, array $entityData): array
    {
        $this->logger->info('开始创建实体', [
            'entity_type' => $entityType,
        ]);

        // 使用Redis分布式锁确保并发安全
        $lockKey = sprintf('entity:create:%s', $entityType);
        $lockValue = uniqid();
        
        try {
            // 尝试获取分布式锁，超时时间5秒，锁定时间10秒
            if (! $this->redisLockService->lock($lockKey, $lockValue, 10, 5)) {
                throw new \RuntimeException('系统繁忙，请稍后再试');
            }

            // 模拟实体创建（在实际应用中，这里应该调用Repository层的方法）
            $entityId = $this->simulateCreateEntity($entityType, $entityData);

            // 提交事务后，触发数据更新事件
            // 注意：事件应该在事务提交后触发，避免因事务回滚导致的事件处理错误
            $this->dispatcher->dispatch(new DataUpdatedEvent('create', $entityType, $entityId, $entityData));

            // 异步处理其他任务
            $this->taskService->logAsync(
                'info',
                '实体创建成功',
                ['entity_type' => $entityType, 'entity_id' => $entityId],
                'entity'
            );

            // 释放分布式锁
            $this->redisLockService->unlock($lockKey, $lockValue);

            $this->logger->info('实体创建成功', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);

            return [
                'success' => true,
                'entity_id' => $entityId,
                'message' => '实体创建成功',
            ];
        } catch (\Throwable $e) {
            // 确保异常情况下也释放锁
            $this->redisLockService->unlock($lockKey, $lockValue);

            $this->logger->error('创建实体失败', [
                'entity_type' => $entityType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 演示更新实体并触发数据更新事件
     *
     * @param string $entityType 实体类型
     * @param int $entityId 实体ID
     * @param array $updateData 更新数据
     * @return array 操作结果
     */
    public function updateEntity(string $entityType, int $entityId, array $updateData): array
    {
        $this->logger->info('开始更新实体', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);

        // 使用Redis分布式锁确保并发安全
        $lockKey = sprintf('entity:update:%s:%d', $entityType, $entityId);
        $lockValue = uniqid();

        try {
            // 尝试获取分布式锁，超时时间3秒，锁定时间5秒
            if (! $this->redisLockService->lock($lockKey, $lockValue, 5, 3)) {
                throw new \RuntimeException('系统繁忙，请稍后再试');
            }

            // 模拟实体更新
            $result = $this->simulateUpdateEntity($entityType, $entityId, $updateData);

            if (! $result) {
                $this->redisLockService->unlock($lockKey, $lockValue);
                return [
                    'success' => false,
                    'message' => '实体不存在或更新失败',
                ];
            }

            // 提交事务后，触发数据更新事件
            $this->dispatcher->dispatch(new DataUpdatedEvent('update', $entityType, $entityId, $updateData));

            // 异步处理日志记录
            $this->taskService->logAsync(
                'info',
                '实体更新成功',
                ['entity_type' => $entityType, 'entity_id' => $entityId, 'changes' => array_keys($updateData)],
                'entity'
            );

            // 释放分布式锁
            $this->redisLockService->unlock($lockKey, $lockValue);

            $this->logger->info('实体更新成功', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);

            return [
                'success' => true,
                'message' => '实体更新成功',
            ];
        } catch (\Throwable $e) {
            // 确保异常情况下也释放锁
            $this->redisLockService->unlock($lockKey, $lockValue);

            $this->logger->error('更新实体失败', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 演示删除实体并触发数据更新事件
     *
     * @param string $entityType 实体类型
     * @param int $entityId 实体ID
     * @return array 操作结果
     */
    public function deleteEntity(string $entityType, int $entityId): array
    {
        $this->logger->info('开始删除实体', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);

        // 使用Redis分布式锁确保并发安全
        $lockKey = sprintf('entity:delete:%s:%d', $entityType, $entityId);
        $lockValue = uniqid();

        try {
            // 尝试获取分布式锁
            if (! $this->redisLockService->lock($lockKey, $lockValue, 5, 3)) {
                throw new \RuntimeException('系统繁忙，请稍后再试');
            }

            // 模拟实体删除
            $result = $this->simulateDeleteEntity($entityType, $entityId);

            if (! $result) {
                $this->redisLockService->unlock($lockKey, $lockValue);
                return [
                    'success' => false,
                    'message' => '实体不存在或删除失败',
                ];
            }

            // 提交事务后，触发数据更新事件
            $this->dispatcher->dispatch(new DataUpdatedEvent('delete', $entityType, $entityId));

            // 异步处理日志记录
            $this->taskService->logAsync(
                'info',
                '实体删除成功',
                ['entity_type' => $entityType, 'entity_id' => $entityId],
                'entity'
            );

            // 释放分布式锁
            $this->redisLockService->unlock($lockKey, $lockValue);

            $this->logger->info('实体删除成功', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);

            return [
                'success' => true,
                'message' => '实体删除成功',
            ];
        } catch (\Throwable $e) {
            // 确保异常情况下也释放锁
            $this->redisLockService->unlock($lockKey, $lockValue);

            $this->logger->error('删除实体失败', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 演示创建评论并触发评论事件
     *
     * @param array $commentData 评论数据
     * @return array 操作结果
     */
    public function createComment(array $commentData): array
    {
        $this->logger->info('开始创建评论');

        try {
            // 模拟评论创建
            $commentId = $this->simulateCreateComment($commentData);

            // 触发新评论事件
            $this->dispatcher->dispatch(new NewCommentEvent($commentId, $commentData));

            // 异步处理日志记录
            $this->taskService->logAsync(
                'info',
                '评论创建成功',
                ['comment_id' => $commentId],
                'comment'
            );

            $this->logger->info('评论创建成功', ['comment_id' => $commentId]);

            return [
                'success' => true,
                'comment_id' => $commentId,
                'message' => '评论创建成功',
            ];
        } catch (\Throwable $e) {
            $this->logger->error('创建评论失败', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * 演示批量异步任务处理
     *
     * @param array $items 待处理项目列表
     * @return array 处理结果
     */
    public function processBatchItems(array $items): array
    {
        $this->logger->info('开始批量处理项目', ['total_items' => count($items)]);

        // 准备任务列表
        $tasks = [];
        foreach ($items as $index => $item) {
            $tasks[] = [
                'task_class' => AsyncLogTask::class,
                'data' => [
                    'level' => 'info',
                    'message' => '处理批量项目',
                    'context' => ['item_index' => $index, 'item_data' => $item],
                    'channel' => 'batch',
                ],
            ];
        }

        // 并发执行任务，限制最大并发数为10
        $results = $this->taskService->dispatchBatchTasks($tasks, 10);

        $successCount = count(array_filter($results));
        $this->logger->info('批量处理完成', [
            'total_items' => count($items),
            'success_items' => $successCount,
            'failed_items' => count($items) - $successCount,
        ]);

        return [
            'total' => count($items),
            'success' => $successCount,
            'failed' => count($items) - $successCount,
            'results' => $results,
        ];
    }

    // 以下方法仅用于演示，实际应用中应调用Repository层

    /**
     * 模拟创建实体
     */
    private function simulateCreateEntity(string $entityType, array $data): int
    {
        // 模拟数据库操作延迟
        usleep(10000); // 10ms
        // 模拟返回ID
        return rand(1000, 9999);
    }

    /**
     * 模拟更新实体
     */
    private function simulateUpdateEntity(string $entityType, int $entityId, array $data): bool
    {
        // 模拟数据库操作延迟
        usleep(5000); // 5ms
        // 模拟总是成功
        return true;
    }

    /**
     * 模拟删除实体
     */
    private function simulateDeleteEntity(string $entityType, int $entityId): bool
    {
        // 模拟数据库操作延迟
        usleep(5000); // 5ms
        // 模拟总是成功
        return true;
    }

    /**
     * 模拟创建评论
     */
    private function simulateCreateComment(array $data): int
    {
        // 模拟数据库操作延迟
        usleep(10000); // 10ms
        // 模拟返回ID
        return rand(10000, 99999);
    }
}