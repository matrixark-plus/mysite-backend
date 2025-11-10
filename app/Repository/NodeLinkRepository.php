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
use Hyperf\DbConnection\Db;

/**
 * 节点链接数据访问层
 * 封装所有与节点链接数据相关的数据库操作.
 */
class NodeLinkRepository extends BaseRepository
{
    /**
     * 根据源节点ID查找链接.
     *
     * @param int $sourceNodeId 源节点ID
     * @return array 链接数据数组
     */
    public function findBySourceNodeId(int $sourceNodeId): array
    {
        return $this->findWithConditionsInternal(['source_node_id' => $sourceNodeId]);
    }

    /**
     * 根据目标节点ID查找链接.
     *
     * @param int $targetNodeId 目标节点ID
     * @return array 链接数据数组
     */
    public function findByTargetNodeId(int $targetNodeId): array
    {
        return $this->findWithConditionsInternal(['target_node_id' => $targetNodeId]);
    }

    /**
     * 删除与指定节点相关的所有链接.
     *
     * @param int $nodeId 节点ID
     * @return int 影响的行数
     */
    public function deleteByNodeId(int $nodeId): int
    {
        return $this->handleDatabaseOperation(
            function () use ($nodeId) {
                // 使用Db类进行数据库操作，避免直接通过model属性
                $modelClass = $this->getModel();
                $tableName = (new $modelClass())->getTable();
                return Db::table($tableName)
                    ->where('source_node_id', $nodeId)
                    ->orWhere('target_node_id', $nodeId)
                    ->delete();
            },
            '删除节点相关链接失败',
            ['node_id' => $nodeId],
            0
        );
    }

    /**
     * 获取模型类名.
     * @return string 模型类名
     */
    protected function getModel(): string
    {
        return NodeLink::class;
    }
}
