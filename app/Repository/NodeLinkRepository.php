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

namespace App\Repository;

use App\Model\NodeLink;
use Exception;
use Hyperf\Database\Model\Collection;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 节点链接数据访问层
 * 封装所有与节点链接数据相关的数据库操作.
 */
class NodeLinkRepository
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 根据ID查找节点链接.
     *
     * @param int $id 链接ID
     * @return null|NodeLink 模型对象或null
     */
    public function findById(int $id): ?NodeLink
    {
        try {
            return NodeLink::find($id);
        } catch (Exception $e) {
            $this->logger->error('根据ID查找节点链接失败: ' . $e->getMessage(), ['link_id' => $id]);
            return null;
        }
    }

    /**
     * 根据源节点ID查找链接.
     *
     * @param int $sourceNodeId 源节点ID
     * @return Collection<int, NodeLink> 模型集合
     */
    public function findBySourceNodeId(int $sourceNodeId): Collection
    {
        try {
            $result = NodeLink::where('source_node_id', $sourceNodeId)->get();
            return $result instanceof Collection ? $result : new Collection();
        } catch (Exception $e) {
            $this->logger->error('根据源节点ID查找链接失败: ' . $e->getMessage(), ['source_node_id' => $sourceNodeId]);
            return new Collection();
        }
    }

    /**
     * 根据目标节点ID查找链接.
     *
     * @param int $targetNodeId 目标节点ID
     * @return Collection<int, NodeLink> 模型集合
     */
    public function findByTargetNodeId(int $targetNodeId): Collection
    {
        try {
            $result = NodeLink::where('target_node_id', $targetNodeId)->get();
            return $result instanceof Collection ? $result : new Collection();
        } catch (Exception $e) {
            $this->logger->error('根据目标节点ID查找链接失败: ' . $e->getMessage(), ['target_node_id' => $targetNodeId]);
            return new Collection();
        }
    }

    /**
     * 创建节点链接.
     *
     * @param array<string, mixed> $data 链接数据
     * @return null|NodeLink 创建的模型对象或null
     */
    public function create(array $data): ?NodeLink
    {
        try {
            return NodeLink::create($data);
        } catch (Exception $e) {
            $this->logger->error('创建节点链接失败: ' . $e->getMessage(), ['data' => $data]);
            return null;
        }
    }

    /**
     * 批量创建节点链接.
     *
     * @param array<array<string, mixed>> $dataSet 链接数据数组
     * @return bool 创建是否成功
     */
    public function batchCreate(array $dataSet): bool
    {
        try {
            $result = NodeLink::insert($dataSet);
            return $result !== false;
        } catch (Exception $e) {
            $this->logger->error('批量创建节点链接失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除节点链接.
     *
     * @param int $id 链接ID
     * @return bool 删除是否成功
     */
    public function delete(int $id): bool
    {
        try {
            $result = NodeLink::destroy($id);
            return $result > 0;
        } catch (Exception $e) {
            $this->logger->error('删除节点链接失败: ' . $e->getMessage(), ['link_id' => $id]);
            return false;
        }
    }

    /**
     * 删除与指定节点相关的所有链接.
     *
     * @param int $nodeId 节点ID
     * @return int 影响的行数
     */
    public function deleteByNodeId(int $nodeId): int
    {
        try {
            return NodeLink::where('source_node_id', $nodeId)->orWhere('target_node_id', $nodeId)->delete();
        } catch (Exception $e) {
            $this->logger->error('删除节点相关链接失败: ' . $e->getMessage(), ['node_id' => $nodeId]);
            return 0;
        }
    }
}