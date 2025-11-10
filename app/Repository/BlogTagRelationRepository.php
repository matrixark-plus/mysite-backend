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

use App\Model\BlogTagRelation;
use Exception;
use Hyperf\Database\Model\Collection;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 博客标签关联数据访问层
 * 封装所有与博客标签关联数据相关的数据库操作.
 */
class BlogTagRelationRepository extends BaseRepository
{
    /**
     * 获取模型实例.
     */
    protected function getModel(): string
    {
        return BlogTagRelation::class;
    }

    /**
     * 根据ID查找博客标签关联记录.
     *
     * @param int $id 关联记录ID
     * @return null|array 数据数组或null
     */
    public function findById(int $id): ?array
    {
        try {
            $model = $this->model->find($id);
            return $model ? $model->toArray() : null;
        } catch (Exception $e) {
            $this->logger->error('根据ID查找博客标签关联记录失败: ' . $e->getMessage(), ['relation_id' => $id]);
            return null;
        }
    }

    /**
     * 根据博客ID获取关联的标签记录.
     *
     * @param int $blogId 博客ID
     * @return array 数据数组
     */
    public function findByBlogId(int $blogId): array
    {
        try {
            $result = BlogTagRelation::where('blog_id', $blogId)->get();
            return $result->toArray();
        } catch (Exception $e) {
            $this->logger->error('根据博客ID获取标签关联失败: ' . $e->getMessage(), ['blog_id' => $blogId]);
            return [];
        }
    }

    /**
     * 根据标签ID获取关联的博客记录.
     *
     * @param int $tagId 标签ID
     * @return array 数据数组
     */
    public function findByTagId(int $tagId): array
    {
        try {
            $result = BlogTagRelation::where('tag_id', $tagId)->get();
            return $result->toArray();
        } catch (Exception $e) {
            $this->logger->error('根据标签ID获取博客关联失败: ' . $e->getMessage(), ['tag_id' => $tagId]);
            return [];
        }
    }

    /**
     * 检查博客和标签是否已关联.
     *
     * @param int $blogId 博客ID
     * @param int $tagId 标签ID
     * @return bool 是否已关联
     */
    public function exists(int $blogId, int $tagId): bool
    {
        try {
            return BlogTagRelation::where('blog_id', $blogId)->where('tag_id', $tagId)->count() > 0;
        } catch (Exception $e) {
            $this->logger->error('检查博客标签关联是否存在失败: ' . $e->getMessage(), ['blog_id' => $blogId, 'tag_id' => $tagId]);
            return false;
        }
    }

    /**
     * 创建博客标签关联记录.
     *
     * @param array<string, mixed> $data 关联数据
     * @return bool 是否创建成功
     */
    public function create(array $data): bool
    {
        try {
            BlogTagRelation::create($data);
            return true;
        } catch (Exception $e) {
            $this->logger->error('创建博客标签关联记录失败: ' . $e->getMessage(), ['data' => $data]);
            return false;
        }
    }

    /**
     * 批量创建博客标签关联记录.
     *
     * @param array<array<string, mixed>> $dataSet 关联数据数组
     * @return bool 创建是否成功
     */
    public function batchCreate(array $dataSet): bool
    {
        try {
            $result = BlogTagRelation::insert($dataSet);
            return $result !== false;
        } catch (Exception $e) {
            $this->logger->error('批量创建博客标签关联记录失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 删除博客标签关联记录.
     *
     * @param int $id 关联记录ID
     * @return bool 删除是否成功
     */
    public function delete(int $id): bool
    {
        try {
            $result = BlogTagRelation::destroy($id);
            return $result > 0;
        } catch (Exception $e) {
            $this->logger->error('删除博客标签关联记录失败: ' . $e->getMessage(), ['relation_id' => $id]);
            return false;
        }
    }

    /**
     * 删除博客的所有标签关联.
     *
     * @param int $blogId 博客ID
     * @return int 影响的行数
     */
    public function deleteByBlogId(int $blogId): int
    {
        try {
            return BlogTagRelation::where('blog_id', $blogId)->delete();
        } catch (Exception $e) {
            $this->logger->error('删除博客标签关联失败: ' . $e->getMessage(), ['blog_id' => $blogId]);
            return 0;
        }
    }

    /**
     * 删除标签的所有博客关联.
     *
     * @param int $tagId 标签ID
     * @return int 影响的行数
     */
    public function deleteByTagId(int $tagId): int
    {
        try {
            return BlogTagRelation::where('tag_id', $tagId)->delete();
        } catch (Exception $e) {
            $this->logger->error('删除标签博客关联失败: ' . $e->getMessage(), ['tag_id' => $tagId]);
            return 0;
        }
    }

    /**
     * 根据博客ID和标签ID数组删除关联.
     *
     * @param int $blogId 博客ID
     * @param array $tagIds 标签ID数组
     * @return int 影响的行数
     */
    public function deleteByBlogAndTagIds(int $blogId, array $tagIds): int
    {
        try {
            return BlogTagRelation::where('blog_id', $blogId)
                ->whereIn('tag_id', $tagIds)
                ->delete();
        } catch (Exception $e) {
            $this->logger->error('根据博客ID和标签ID数组删除关联失败: ' . $e->getMessage(), [
                'blog_id' => $blogId,
                'tag_ids' => $tagIds,
            ]);
            return 0;
        }
    }
}
