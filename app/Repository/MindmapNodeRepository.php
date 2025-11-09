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

use App\Model\MindmapNode;
use Exception;
use Hyperf\Database\Model\Collection;
use Hyperf\Di\Annotation\Inject;
use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;

/**
 * 脑图节点数据访问层
 * 封装所有与脑图节点数据相关的数据库操作.
 */
class MindmapNodeRepository
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 根据ID查找脑图节点.
     *
     * @param int $id 节点ID
     * @return array|null 节点数据数组或null
     */
    public function findById(int $id): ?array
    {
        try {
            $node = MindmapNode::find($id);
            return $node ? $node->toArray() : null;
        } catch (Exception $e) {
            $this->logger->error('根据ID查找脑图节点失败: ' . $e->getMessage(), ['node_id' => $id]);
            return null;
        }
    }

    /**
     * 根据根节点ID获取所有节点.
     *
     * @param int $rootId 根节点ID
     * @return Collection<int, MindmapNode> 模型集合
     */
    public function findByRootId(int $rootId): Collection
    {
        try {
            $result = MindmapNode::where('root_id', $rootId)->get();
            return $result instanceof Collection ? $result : new Collection();
        } catch (Exception $e) {
            $this->logger->error('根据根节点ID获取节点失败: ' . $e->getMessage(), ['root_id' => $rootId]);
            return new Collection();
        }
    }

    /**
     * 获取父节点的所有子节点.
     *
     * @param int $parentId 父节点ID
     * @return Collection<int, MindmapNode> 模型集合
     */
    public function findChildren(int $parentId): Collection
    {
        try {
            $result = MindmapNode::where('parent_id', $parentId)->get();
            return $result instanceof Collection ? $result : new Collection();
        } catch (Exception $e) {
            $this->logger->error('获取子节点失败: ' . $e->getMessage(), ['parent_id' => $parentId]);
            return new Collection();
        }
    }

    /**
     * 创建脑图节点.
     *
     * @param array<string, mixed> $data 节点数据
     * @return null|MindmapNode 创建的模型对象或null
     */
    public function create(array $data): ?MindmapNode
    {
        try {
            return MindmapNode::create($data);
        } catch (Exception $e) {
            $this->logger->error('创建脑图节点失败: ' . $e->getMessage(), ['data' => $data]);
            return null;
        }
    }

    /**
     * 批量创建脑图节点.
     *
     * @param array<array<string, mixed>> $dataSet 节点数据数组
     * @return bool 创建是否成功
     */
    public function batchCreate(array $dataSet): bool
    {
        try {
            $result = MindmapNode::insert($dataSet);
            return $result !== false;
        } catch (Exception $e) {
            $this->logger->error('批量创建脑图节点失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 更新脑图节点.
     *
     * @param int $id 节点ID
     * @param array<string, mixed> $data 更新数据
     * @return bool 更新是否成功
     */
    public function update(int $id, array $data): bool
    {
        try {
            $result = MindmapNode::where('id', $id)->update($data);
            return $result > 0;
        } catch (Exception $e) {
            $this->logger->error('更新脑图节点失败: ' . $e->getMessage(), ['node_id' => $id, 'data' => $data]);
            return false;
        }
    }

    /**
     * 删除脑图节点.
     *
     * @param int $id 节点ID
     * @return bool 删除是否成功
     */
    public function delete(int $id): bool
    {
        try {
            $result = MindmapNode::destroy($id);
            return $result > 0;
        } catch (Exception $e) {
            $this->logger->error('删除脑图节点失败: ' . $e->getMessage(), ['node_id' => $id]);
            return false;
        }
    }

    /**
     * 删除根节点的所有子节点.
     *
     * @param int $rootId 根节点ID
     * @return int 影响的行数
     */
    public function deleteByRootId(int $rootId): int
    {
        try {
            return MindmapNode::where('root_id', $rootId)->delete();
        } catch (Exception $e) {
            $this->logger->error('删除根节点的子节点失败: ' . $e->getMessage(), ['root_id' => $rootId]);
            return 0;
        }
    }

    /**
     * 获取根节点列表
     *
     * @param array $params 查询参数
     * @return array 包含总数和列表数据
     */
    public function getRootNodes(array $params = []): array
    {
        try {
            $query = MindmapNode::query()
                ->where('parent_id', 0) // 根节点的父ID为0
                ->where('status', 1);   // 只获取已发布的

            // 应用筛选条件
            if (isset($params['keyword']) && $params['keyword']) {
                $query->where('title', 'like', '%' . $params['keyword'] . '%');
            }

            // 应用排序
            $sortBy = $params['sort_by'] ?? 'created_at';
            $sortOrder = $params['sort_order'] ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // 分页
            $page = $params['page'] ?? 1;
            $pageSize = $params['page_size'] ?? 20;

            // 获取总数
            $total = $query->count();

            // 获取列表
            $list = $query
                ->forPage($page, $pageSize)
                ->select([
                    'id', 'title', 'description', 'cover_image',
                    'created_at', 'updated_at', 'view_count',
                ])
                ->get()
                ->toArray();

            return [
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'list' => $list,
            ];
        } catch (Exception $e) {
            $this->logger->error('获取脑图根节点列表异常: ' . $e->getMessage(), ['params' => $params]);
            throw $e;
        }
    }

    /**
     * 获取所有节点（包括根节点和子节点）
     *
     * @param int $rootId 根节点ID
     * @return array 节点数组
     */
    public function getAllNodes(int $rootId): array
    {
        try {
            $nodes = MindmapNode::query()
                ->where(function ($query) use ($rootId) {
                    $query->where('id', $rootId) // 包含根节点
                        ->orWhere('root_id', $rootId); // 以及其所有子节点
                })
                ->where('status', 1)
                ->get()
                ->toArray();

            $result = [];
            foreach ($nodes as $node) {
                $result[$node['id']] = $node;
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('获取所有脑图节点异常: ' . $e->getMessage(), ['rootId' => $rootId]);
            throw $e;
        }
    }

    /**
     * 获取节点间的关系
     *
     * @param int $rootId 根节点ID
     * @return array 边数组
     */
    public function getEdges(int $rootId): array
    {
        try {
            // 使用原生查询获取边关系
            $edges = Db::table('mind_map_edges')
                ->where('root_id', $rootId)
                ->get()
                ->toArray();

            $result = [];
            foreach ($edges as $edge) {
                $result[] = [
                    'id' => $edge->id,
                    'source' => $edge->source_id,
                    'target' => $edge->target_id,
                    'label' => $edge->label,
                    'direction' => $edge->direction,
                ];
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('获取脑图边关系异常: ' . $e->getMessage(), ['rootId' => $rootId]);
            throw $e;
        }
    }

    /**
     * 增加浏览次数
     *
     * @param int $nodeId 节点ID
     */
    public function incrementViewCount(int $nodeId): void
    {
        try {
            MindmapNode::query()
                ->where('id', $nodeId)
                ->increment('view_count');
        } catch (Exception $e) {
            $this->logger->error('增加脑图节点浏览次数异常: ' . $e->getMessage(), ['nodeId' => $nodeId]);
            throw $e;
        }
    }
}