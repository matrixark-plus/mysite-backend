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

use App\Model\MindmapRoot;
use Exception;
use Hyperf\Database\Model\Collection;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 脑图根节点数据访问层
 * 封装所有与脑图根节点数据相关的数据库操作.
 */
class MindmapRootRepository
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 根据ID查找脑图根节点.
     *
     * @param int $id 根节点ID
     * @return null|MindmapRoot 模型对象或null
     */
    public function findById(int $id): ?MindmapRoot
    {
        try {
            return MindmapRoot::find($id);
        } catch (Exception $e) {
            $this->logger->error('根据ID查找脑图根节点失败: ' . $e->getMessage(), ['root_id' => $id]);
            return null;
        }
    }

    /**
     * 根据创建者ID获取脑图列表.
     *
     * @param int $creatorId 创建者ID
     * @param array<string, string> $order 排序条件
     * @return Collection<int, MindmapRoot> 模型集合
     */
    public function findByCreatorId(int $creatorId, array $order = ['created_at' => 'desc']): Collection
    {
        try {
            $query = MindmapRoot::where('creator_id', $creatorId);

            foreach ($order as $field => $direction) {
                $query = $query->orderBy($field, $direction);
            }

            $result = $query->get();
            return $result instanceof Collection ? $result : new Collection();
        } catch (Exception $e) {
            $this->logger->error('根据创建者ID获取脑图列表失败: ' . $e->getMessage(), ['creator_id' => $creatorId]);
            return new Collection();
        }
    }

    /**
     * 获取公开的脑图列表.
     *
     * @param array<string, string> $order 排序条件
     * @return Collection<int, MindmapRoot> 模型集合
     */
    public function findPublicMindmaps(array $order = ['created_at' => 'desc']): Collection
    {
        try {
            $query = MindmapRoot::where('is_public', true);

            foreach ($order as $field => $direction) {
                $query = $query->orderBy($field, $direction);
            }

            $result = $query->get();
            return $result instanceof Collection ? $result : new Collection();
        } catch (Exception $e) {
            $this->logger->error('获取公开脑图列表失败: ' . $e->getMessage());
            return new Collection();
        }
    }

    /**
     * 根据条件获取脑图根节点列表.
     *
     * @param array<string, mixed> $conditions 查询条件
     * @param array<string> $columns 查询字段
     * @param array<string, string> $order 排序条件
     * @return Collection<int, MindmapRoot> 模型集合
     */
    public function findAllBy(array $conditions = [], array $columns = ['*'], array $order = ['created_at' => 'desc']): Collection
    {
        try {
            $query = MindmapRoot::query();

            if (! empty($conditions)) {
                $query = $query->where($conditions);
            }

            foreach ($order as $field => $direction) {
                $query = $query->orderBy($field, $direction);
            }

            $result = $query->select($columns)->get();
            return $result instanceof Collection ? $result : new Collection();
        } catch (Exception $e) {
            $this->logger->error('获取脑图根节点列表失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return new Collection();
        }
    }

    /**
     * 创建脑图根节点.
     *
     * @param array<string, mixed> $data 根节点数据
     * @return null|MindmapRoot 创建的模型对象或null
     */
    public function create(array $data): ?MindmapRoot
    {
        try {
            return MindmapRoot::create($data);
        } catch (Exception $e) {
            $this->logger->error('创建脑图根节点失败: ' . $e->getMessage(), ['data' => $data]);
            return null;
        }
    }

    /**
     * 更新脑图根节点.
     *
     * @param int $id 根节点ID
     * @param array<string, mixed> $data 更新数据
     * @return bool 更新是否成功
     */
    public function update(int $id, array $data): bool
    {
        try {
            $result = MindmapRoot::where('id', $id)->update($data);
            return $result > 0;
        } catch (Exception $e) {
            $this->logger->error('更新脑图根节点失败: ' . $e->getMessage(), ['root_id' => $id, 'data' => $data]);
            return false;
        }
    }

    /**
     * 删除脑图根节点.
     *
     * @param int $id 根节点ID
     * @return bool 删除是否成功
     */
    public function delete(int $id): bool
    {
        try {
            $result = MindmapRoot::destroy($id);
            return $result > 0;
        } catch (Exception $e) {
            $this->logger->error('删除脑图根节点失败: ' . $e->getMessage(), ['root_id' => $id]);
            return false;
        }
    }

    /**
     * 切换脑图公开状态.
     *
     * @param int $id 根节点ID
     * @param bool $isPublic 是否公开
     * @return bool 更新是否成功
     */
    public function togglePublicStatus(int $id, bool $isPublic): bool
    {
        try {
            $result = MindmapRoot::where('id', $id)->update(['is_public' => $isPublic]);
            return $result > 0;
        } catch (Exception $e) {
            $this->logger->error('切换脑图公开状态失败: ' . $e->getMessage(), ['root_id' => $id, 'is_public' => $isPublic]);
            return false;
        }
    }
}