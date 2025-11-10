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

namespace App\Service;

use App\Repository\BlogTagRelationRepository;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 博客标签关系服务层
 * 处理博客与标签之间的关联业务逻辑.
 */
class BlogTagRelationService
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var BlogTagRelationRepository
     */
    protected $blogTagRelationRepository;

    /**
     * 为博客添加标签.
     *
     * @param int $blogId 博客ID
     * @param array $tagIds 标签ID数组
     * @return array 操作结果
     */
    public function addTagsToBlog(int $blogId, array $tagIds): array
    {
        try {
            // 验证参数
            if ($blogId <= 0 || empty($tagIds)) {
                return [
                    'success' => false,
                    'message' => '无效的参数',
                ];
            }

            // 过滤无效的标签ID
            $tagIds = array_filter($tagIds, function ($id) {
                return is_numeric($id) && $id > 0;
            });

            if (empty($tagIds)) {
                return [
                    'success' => false,
                    'message' => '没有有效的标签ID',
                ];
            }

            // 创建关联数据
            $relations = [];
            foreach ($tagIds as $tagId) {
                // 检查是否已存在
                if (! $this->blogTagRelationRepository->exists($blogId, (int) $tagId)) {
                    $relations[] = [
                        'blog_id' => $blogId,
                        'tag_id' => (int) $tagId,
                    ];
                }
            }

            if (empty($relations)) {
                return [
                    'success' => true,
                    'message' => '所有标签已经关联到该博客',
                ];
            }

            // 批量创建关联
            $result = $this->blogTagRelationRepository->batchCreate($relations);

            if ($result) {
                return [
                    'success' => true,
                    'message' => '标签添加成功',
                    'data' => [
                        'added_count' => count($relations),
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => '标签添加失败',
            ];
        } catch (Exception $e) {
            $this->logger->error('为博客添加标签异常: ' . $e->getMessage(), [
                'blog_id' => $blogId,
                'tag_ids' => $tagIds,
            ]);
            return [
                'success' => false,
                'message' => '系统异常，请稍后重试',
            ];
        }
    }

    /**
     * 更新博客的标签
     * 会先删除原有的关联，然后添加新的关联.
     *
     * @param int $blogId 博客ID
     * @param array $tagIds 标签ID数组
     * @return array 操作结果
     */
    public function updateBlogTags(int $blogId, array $tagIds): array
    {
        try {
            // 验证参数
            if ($blogId <= 0) {
                return [
                    'success' => false,
                    'message' => '无效的博客ID',
                ];
            }

            // 过滤无效的标签ID
            $tagIds = array_filter($tagIds, function ($id) {
                return is_numeric($id) && $id > 0;
            });

            // 删除原有的关联
            $this->blogTagRelationRepository->deleteByBlogId($blogId);

            // 如果没有新的标签，直接返回成功
            if (empty($tagIds)) {
                return [
                    'success' => true,
                    'message' => '博客标签已清空',
                ];
            }

            // 创建新的关联数据
            $relations = [];
            foreach ($tagIds as $tagId) {
                $relations[] = [
                    'blog_id' => $blogId,
                    'tag_id' => (int) $tagId,
                ];
            }

            // 批量创建关联
            $result = $this->blogTagRelationRepository->batchCreate($relations);

            if ($result) {
                return [
                    'success' => true,
                    'message' => '博客标签更新成功',
                    'data' => [
                        'tag_count' => count($relations),
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => '博客标签更新失败',
            ];
        } catch (Exception $e) {
            $this->logger->error('更新博客标签异常: ' . $e->getMessage(), [
                'blog_id' => $blogId,
                'tag_ids' => $tagIds,
            ]);
            return [
                'success' => false,
                'message' => '系统异常，请稍后重试',
            ];
        }
    }

    /**
     * 从博客中移除标签.
     *
     * @param int $blogId 博客ID
     * @param array $tagIds 标签ID数组
     * @return array 操作结果
     */
    public function removeTagsFromBlog(int $blogId, array $tagIds): array
    {
        try {
            // 验证参数
            if ($blogId <= 0 || empty($tagIds)) {
                return [
                    'success' => false,
                    'message' => '无效的参数',
                ];
            }

            // 过滤无效的标签ID
            $tagIds = array_filter($tagIds, function ($id) {
                return is_numeric($id) && $id > 0;
            });

            if (empty($tagIds)) {
                return [
                    'success' => false,
                    'message' => '没有有效的标签ID',
                ];
            }

            // 删除指定的标签关联
            $result = $this->blogTagRelationRepository->deleteByBlogAndTagIds($blogId, $tagIds);

            if ($result) {
                return [
                    'success' => true,
                    'message' => '标签移除成功',
                ];
            }

            return [
                'success' => false,
                'message' => '标签移除失败',
            ];
        } catch (Exception $e) {
            $this->logger->error('从博客中移除标签异常: ' . $e->getMessage(), [
                'blog_id' => $blogId,
                'tag_ids' => $tagIds,
            ]);
            return [
                'success' => false,
                'message' => '系统异常，请稍后重试',
            ];
        }
    }

    /**
     * 获取博客的标签ID列表.
     *
     * @param int $blogId 博客ID
     * @return array 标签ID列表
     */
    public function getBlogTagIds(int $blogId): array
    {
        try {
            if ($blogId <= 0) {
                return [];
            }

            $relations = $this->blogTagRelationRepository->findByBlogId($blogId);
            $tagIds = [];

            foreach ($relations as $relation) {
                $tagIds[] = $relation->tag_id;
            }

            return $tagIds;
        } catch (Exception $e) {
            $this->logger->error('获取博客标签ID异常: ' . $e->getMessage(), ['blog_id' => $blogId]);
            return [];
        }
    }

    /**
     * 获取标签下的博客ID列表.
     *
     * @param int $tagId 标签ID
     * @return array 博客ID列表
     */
    public function getTagBlogIds(int $tagId): array
    {
        try {
            if ($tagId <= 0) {
                return [];
            }

            $relations = $this->blogTagRelationRepository->findByTagId($tagId);
            $blogIds = [];

            foreach ($relations as $relation) {
                $blogIds[] = $relation->blog_id;
            }

            return $blogIds;
        } catch (Exception $e) {
            $this->logger->error('获取标签博客ID异常: ' . $e->getMessage(), ['tag_id' => $tagId]);
            return [];
        }
    }

    /**
     * 检查博客是否包含指定标签.
     *
     * @param int $blogId 博客ID
     * @param int $tagId 标签ID
     * @return bool 是否包含
     */
    public function hasTag(int $blogId, int $tagId): bool
    {
        try {
            return $this->blogTagRelationRepository->exists($blogId, $tagId);
        } catch (Exception $e) {
            $this->logger->error('检查博客标签异常: ' . $e->getMessage(), [
                'blog_id' => $blogId,
                'tag_id' => $tagId,
            ]);
            return false;
        }
    }
}
