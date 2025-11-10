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

namespace App\Listener;

use App\Event\DataUpdatedEvent;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Redis\Redis;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * 数据更新监听器
 * 处理数据更新事件，实现缓存更新、日志记录等异步操作.
 */
/**
 * @Listener
 */
class DataUpdatedListener implements ListenerInterface
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var Redis
     */
    protected $redis;

    /**
     * 返回需要监听的事件列表.
     */
    public function listen(): array
    {
        return [
            DataUpdatedEvent::class,
        ];
    }

    /**
     * 处理数据更新事件.
     *
     * @param DataUpdatedEvent $event
     */
    public function process(object $event): void
    {
        // 添加类型检查
        if (! $event instanceof DataUpdatedEvent) {
            return;
        }

        try {
            // 解析事件数据
            $action = $event->getAction();
            $entityType = $event->getEntityType();
            $entityId = $event->getEntityId();
            $changedData = $event->getChangedData();
            $timestamp = $event->getTimestamp();

            // 记录数据变更日志
            $this->logDataChange($action, $entityType, $entityId, $changedData, $timestamp);

            // 根据实体类型执行不同的缓存更新操作
            $this->updateCache($action, $entityType, $entityId, $changedData);

            // 异步处理其他需要的操作
            // 例如：发送通知、更新统计数据等

            $this->logger->info('数据更新事件处理成功', [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('处理数据更新事件失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 记录数据变更日志.
     *
     * @param string $action 操作类型
     * @param string $entityType 实体类型
     * @param int $entityId 实体ID
     * @param array $changedData 变更数据
     * @param int $timestamp 时间�?     */
    private function logDataChange(string $action, string $entityType, int $entityId, array $changedData, int $timestamp): void
    {
        try {
            // 构建变更日志数据
            $logData = [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'changed_data' => $changedData,
                'timestamp' => $timestamp,
                'datetime' => date('Y-m-d H:i:s', $timestamp),
            ];

            // 记录变更日志到Redis队列，供异步处理
            $this->redis->lPush('data_changes_queue', json_encode($logData));

            // 也记录到日志文件
            $this->logger->info('数据变更记录', $logData);
        } catch (Throwable $e) {
            // 确保日志记录失败不会影响主要流程
            $this->logger->error('记录数据变更日志失败', [
                'error' => $e->getMessage(),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
        }
    }

    /**
     * 更新缓存.
     *
     * @param string $action 操作类型
     * @param string $entityType 实体类型
     * @param int $entityId 实体ID
     * @param array $changedData 变更数据
     */
    private function updateCache(string $action, string $entityType, int $entityId, array $changedData): void
    {
        try {
            // 根据实体类型和操作生成缓存键
            $cacheKey = $this->getEntityCacheKey($entityType, $entityId);

            switch ($action) {
                case 'create':
                    // 创建实体时，缓存可能不需要立即更�?                    // 等待读取时再缓存
                    break;
                case 'update':
                    // 更新实体时，清除对应的缓存，下次读取时会重新生成
                    $this->redis->del($cacheKey);
                    // 可选：如果数据变化不频繁且更新成本低，可以直接更新缓存
                    if (! empty($changedData)) {
                        // 从数据库获取最新数据并更新缓存
                        // 这里为了演示，简化处理，实际应该根据具体业务从Repository获取数据
                        $this->logger->info('缓存已清理', ['key' => $cacheKey]);
                    }
                    break;
                case 'delete':
                    // 删除实体时，彻底清除缓存
                    $this->redis->del($cacheKey);
                    // 也可以清除相关的列表缓存
                    $this->clearRelatedListCache($entityType);
                    break;
            }
        } catch (Throwable $e) {
            // 确保缓存更新失败不会影响主要流程
            $this->logger->error('更新缓存失败', [
                'error' => $e->getMessage(),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
        }
    }

    /**
     * 获取实体的缓存键.
     *
     * @param string $entityType 实体类型
     * @param int $entityId 实体ID
     * @return string 缓存�?     */
    private function getEntityCacheKey(string $entityType, int $entityId): string
    {
        return sprintf('entity:%s:%d', $entityType, $entityId);
    }

    /**
     * 清除相关的列表缓�?
     *
     * @param string $entityType 实体类型
     */
    private function clearRelatedListCache(string $entityType): void
    {
        // 清除可能包含该实体的列表缓存
        // 例如：最近更新列表、热门列表等
        $pattern = sprintf('list:%s:*', $entityType);
        $keys = $this->redis->keys($pattern);

        if (! empty($keys)) {
            $this->redis->del(...$keys);
            $this->logger->info('相关列表缓存已清理', ['pattern' => $pattern, 'count' => count($keys)]);
        }
    }
}

