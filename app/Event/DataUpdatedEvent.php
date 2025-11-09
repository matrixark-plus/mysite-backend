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

namespace App\Event;

/**
 * 数据更新事件
 * 当重要数据被更新时触发此事件，用于异步处理和确保最终一致性
 */
class DataUpdatedEvent
{
    /**
     * 操作类型
     *
     * @var string
     */
    protected $action;

    /**
     * 实体类型
     *
     * @var string
     */
    protected $entityType;

    /**
     * 实体ID
     *
     * @var int
     */
    protected $entityId;

    /**
     * 数据变化
     *
     * @var array
     */
    protected $changedData;

    /**
     * 操作时间戳
     *
     * @var int
     */
    protected $timestamp;

    /**
     * 构造函数
     *
     * @param string $action 操作类型（create/update/delete）
     * @param string $entityType 实体类型（blog/work/user等）
     * @param int $entityId 实体ID
     * @param array $changedData 数据变化
     */
    public function __construct(string $action, string $entityType, int $entityId, array $changedData = [])
    {
        $this->action = $action;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->changedData = $changedData;
        $this->timestamp = time();
    }

    /**
     * 获取操作类型
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * 获取实体类型
     */
    public function getEntityType(): string
    {
        return $this->entityType;
    }

    /**
     * 获取实体ID
     */
    public function getEntityId(): int
    {
        return $this->entityId;
    }

    /**
     * 获取数据变化
     */
    public function getChangedData(): array
    {
        return $this->changedData;
    }

    /**
     * 获取操作时间戳
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }
}