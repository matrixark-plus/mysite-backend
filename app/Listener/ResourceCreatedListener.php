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

use Hyperf\Event\Contract\ListenerInterface;
use Psr\Container\ContainerInterface;

/**
 * 资源创建事件监听器.
 */
class ResourceCreatedListener implements ListenerInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * 返回需要监听的事件列表.
     */
    public function listen(): array
    {
        // 这里可以添加实际的资源创建事件
        // 由于项目中可能没有定义具体的事件类，我们暂时返回一个空数组
        // 实际使用时，应该返回项目中定义的资源创建事件类
        return [];
    }

    /**
     * 处理事件.
     * @param object $event 事件对象
     */
    public function process(object $event): void
    {
        // 处理资源创建事件
        // 布隆过滤器功能已移除
    }

    /**
     * 静态方法，布隆过滤器功能已移除.
     * @param mixed $resourceId
     */
    public static function updateBloomFilter(string $resourceType, $resourceId): void
    {
        // 布隆过滤器功能已移除
    }
}
