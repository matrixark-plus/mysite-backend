<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\NodeLinks;

/**
 * 节点链接数据访问层
 * 处理节点链接相关的数据库操作
 */
class NodeLinksRepository extends BaseRepository
{
    /**
     * 获取模型类名
     * @return string 模型类名
     */
    protected function getModel(): string
    {
        return NodeLinks::class;
    }

    /**
     * 根据源节点ID和目标节点ID查找链接
     * @param int $sourceNodeId 源节点ID
     * @param int $targetNodeId 目标节点ID
     * @return array|null 链接数据数组或null
     */
    public function findByNodes(int $sourceNodeId, int $targetNodeId): ?array
    {
        $results = $this->findWithConditionsInternal([
            'source_node_id' => $sourceNodeId,
            'target_node_id' => $targetNodeId
        ]);
        return $results[0] ?? null;
    }

    /**
     * 根据脑图根节点ID获取所有链接
     * @param int $rootId 根节点ID
     * @return array 链接数据数组
     */
    public function getLinksByRootId(int $rootId): array
    {
        return $this->handleDatabaseOperation(
            function () use ($rootId) {
                return $this->model->query()
                    ->leftJoin('mindmap_nodes as source_nodes', 'node_links.source_node_id', '=', 'source_nodes.id')
                    ->leftJoin('mindmap_nodes as target_nodes', 'node_links.target_node_id', '=', 'target_nodes.id')
                    ->where('source_nodes.root_id', $rootId)
                    ->orWhere('target_nodes.root_id', $rootId)
                    ->select('node_links.*')
                    ->get()
                    ->toArray();
            },
            '根据脑图根节点ID获取链接失败',
            ['root_id' => $rootId],
            []
        );
    }

    /**
     * 根据节点ID删除相关链接
     * @param int $nodeId 节点ID
     * @return int 影响的行数
     */
    public function deleteByNodeId(int $nodeId): int
    {
        return $this->handleDatabaseOperation(
            function () use ($nodeId) {
                return $this->model->query()
                    ->where('source_node_id', $nodeId)
                    ->orWhere('target_node_id', $nodeId)
                    ->delete();
            },
            '根据节点ID删除链接失败',
            ['node_id' => $nodeId],
            0
        );
    }

    /**
     * 根据脑图根节点ID删除所有链接
     * @param int $rootId 根节点ID
     * @return int 影响的行数
     */
    public function deleteByRootId(int $rootId): int
    {
        return $this->handleDatabaseOperation(
            function () use ($rootId) {
                // 先获取该脑图下所有节点ID
                $nodeIds = $this->handleDatabaseOperation(
                    function () use ($rootId) {
                        $result = $this->model->query()
                            ->getConnection()
                            ->table('mindmap_nodes')
                            ->where('root_id', $rootId)
                            ->pluck('id')
                            ->toArray();
                        return $result ?? [];
                    },
                    '获取脑图节点ID失败',
                    ['root_id' => $rootId],
                    []
                );
                
                if (empty($nodeIds)) {
                    return 0;
                }
                
                // 删除相关链接
                return $this->model->query()
                    ->whereIn('source_node_id', $nodeIds)
                    ->orWhereIn('target_node_id', $nodeIds)
                    ->delete();
            },
            '根据脑图根节点ID删除链接失败',
            ['root_id' => $rootId],
            0
        );
    }
}