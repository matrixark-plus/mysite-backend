<?php

namespace App\Repository;

use App\Model\NodeLinks;
use Hyperf\DbConnection\Db;

/**
 * 节点链接数据访问层
 * 处理节点链接相关的数据库操作
 */
class NodeLinksRepository
{
    /**
     * 根据ID查找节点链接
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        return NodeLinks::query()
            ->find($id)?->toArray();
    }

    /**
     * 根据源节点ID和目标节点ID查找链接
     * @param int $sourceNodeId
     * @param int $targetNodeId
     * @return array|null
     */
    public function findByNodes(int $sourceNodeId, int $targetNodeId): ?array
    {
        return NodeLinks::query()
            ->where('source_node_id', $sourceNodeId)
            ->where('target_node_id', $targetNodeId)
            ->first()?->toArray();
    }

    /**
     * 根据脑图根节点ID获取所有链接
     * @param int $rootId
     * @return array
     */
    public function getLinksByRootId(int $rootId): array
    {
        return NodeLinks::query()
            ->leftJoin('mindmap_nodes as source_nodes', 'node_links.source_node_id', '=', 'source_nodes.id')
            ->leftJoin('mindmap_nodes as target_nodes', 'node_links.target_node_id', '=', 'target_nodes.id')
            ->where('source_nodes.root_id', $rootId)
            ->orWhere('target_nodes.root_id', $rootId)
            ->select('node_links.*')
            ->get()
            ->toArray();
    }

    /**
     * 创建节点链接
     * @param array $data
     * @return array|null
     */
    public function create(array $data): ?array
    {
        $link = NodeLinks::query()->create($data);
        return $link ? $link->toArray() : null;
    }

    /**
     * 批量创建节点链接
     * @param array $data
     * @return array
     */
    public function batchCreate(array $data): array
    {
        $results = [];
        
        foreach ($data as $linkData) {
            $link = NodeLinks::query()->create($linkData);
            if ($link) {
                $results[] = $link->toArray();
            }
        }
        
        return $results;
    }

    /**
     * 更新节点链接
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        return NodeLinks::query()
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * 删除节点链接
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return NodeLinks::query()
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * 根据节点ID删除相关链接
     * @param int $nodeId
     * @return int
     */
    public function deleteByNodeId(int $nodeId): int
    {
        return NodeLinks::query()
            ->where('source_node_id', $nodeId)
            ->orWhere('target_node_id', $nodeId)
            ->delete();
    }

    /**
     * 根据脑图根节点ID删除所有链接
     * @param int $rootId
     * @return int
     */
    public function deleteByRootId(int $rootId): int
    {
        // 先获取该脑图下所有节点ID
        $nodeIds = Db::table('mindmap_nodes')
            ->where('root_id', $rootId)
            ->pluck('id')
            ->toArray();
        
        if (empty($nodeIds)) {
            return 0;
        }
        
        // 删除相关链接
        return NodeLinks::query()
            ->whereIn('source_node_id', $nodeIds)
            ->orWhereIn('target_node_id', $nodeIds)
            ->delete();
    }
}