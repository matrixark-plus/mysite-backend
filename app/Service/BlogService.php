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

use App\Repository\BlogCategoryRepository;
use App\Repository\BlogRepository;
use App\Repository\BlogTagRelationRepository;
use App\Repository\BlogTagRepository;
use Exception;
use Hyperf\Cache\Cache;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

class BlogService
{
    // 默认发布状态
    protected const STATUS_PUBLISHED = 1;
    
    /**
     * @Inject
     * @var BlogRepository
     */
    protected $blogRepository;

    /**
     * @Inject
     * @var BlogCategoryRepository
     */
    protected $blogCategoryRepository;

    /**
     * @Inject
     * @var BlogTagRepository
     */
    protected $blogTagRepository;

    /**
     * @Inject
     * @var BlogTagRelationRepository
     */
    protected $blogTagRelationRepository;

    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var Cache
     */
    protected $cache;

    /**
     * @Inject
     * @var TaskService
     */
    protected $taskService;

    /**
     * 获取博客列表.
     * @param array $params 查询参数
     */
    public function getBlogs(array $params): array
    {
        // 构建缓存键
        $cacheKey = 'blog:list:' . md5(json_encode([
            'category' => $params['category_id'] ?? 0,
            'keyword' => $params['keyword'] ?? '',
            'sort_by' => $params['sort_by'] ?? 'created_at',
            'sort_order' => $params['sort_order'] ?? 'desc',
            'page' => $params['page'] ?? 1,
            'page_size' => $params['page_size'] ?? 10,
        ]));

        // 尝试从缓存获取
        $cachedResult = $this->cache->get($cacheKey);
        if ($cachedResult) {
            return $cachedResult;
        }

        // 使用Repository获取博客列表
        $result = $this->blogRepository->findAll($params);

        // 缓存结果，设置5分钟过期
        $this->cache->set($cacheKey, $result, 300);

        return $result;
    }

    /**
     * 根据ID获取博客详情.
     * @param int $id 博客ID
     * @param bool $incrementViews 是否增加阅读量
     */
    public function getBlogById(int $id, bool $incrementViews = true): ?array
    {
        // 构建缓存键
        $cacheKey = 'blog:detail:' . $id;

        // 尝试从缓存获取
        $cachedBlog = $this->cache->get($cacheKey);
        if ($cachedBlog) {
            // 如果需要增加阅读量
            if ($incrementViews) {
                $this->incrementBlogViewCount($id);
            }
            return $cachedBlog;
        }

        // 使用Repository获取博客详情
        $blog = $this->blogRepository->findById($id);

        if ($blog) {
            // 缓存博客详情，设置10分钟过期
            $this->cache->set($cacheKey, $blog, 600);

            if ($incrementViews) {
                $this->incrementBlogViewCount($id);
            }
        }

        return $blog;
    }

    /**
     * 创建博客.
     * @param array $data 博客数据
     */
    public function createBlog(array $data): array
    {
        // 准备创建数据
        $createData = [
            'title' => $data['title'],
            'slug' => $this->generateSlug($data['title']),
            'content' => $data['content'],
            'summary' => $data['summary'] ?? $this->generateSummary($data['content']),
            'category_id' => $data['category_id'],
            'author_id' => $data['author_id'],
            'status' => $data['status'] ?? self::STATUS_PUBLISHED,
            'is_recommended' => $data['is_recommended'] ?? false,
            'cover_image' => $data['cover_image'] ?? '',
        ];

        // 使用Repository创建博客
        $blog = $this->blogRepository->create($createData);

        // 记录日志
        $this->logger->info('创建博客成功: ' . $blog['id'] . ' - ' . $blog['title']);

        // 处理标签关联
        $tags = $data['tags'] ?? [];
        if (! empty($tags)) {
            $this->saveBlogTags($blog['id'], $tags);
        }

        // 清除相关缓存
        $this->clearBlogCache();

        return $blog;
    }

    /**
     * 更新博客.
     * @param int $id 博客ID
     * @param array $data 更新数据
     */
    public function updateBlog(int $id, array $data): ?array
    {
        // 准备更新数据
        $updateData = [];
        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
            $updateData['slug'] = $this->generateSlug($data['title'], $id);
        }
        if (isset($data['content'])) {
            $updateData['content'] = $data['content'];
            // 如果没有提供摘要，重新生成
            if (! isset($data['summary'])) {
                $updateData['summary'] = $this->generateSummary($data['content']);
            }
        }
        if (isset($data['summary'])) {
            $updateData['summary'] = $data['summary'];
        }
        if (isset($data['category_id'])) {
            $updateData['category_id'] = $data['category_id'];
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        if (isset($data['is_recommended'])) {
            $updateData['is_recommended'] = $data['is_recommended'];
        }
        if (isset($data['cover_image'])) {
            $updateData['cover_image'] = $data['cover_image'];
        }
        if (isset($data['published_at'])) {
            $updateData['published_at'] = $data['published_at'];
        }
        $updateData['updated_at'] = time();

        // 使用Repository更新博客
        $updatedBlog = $this->blogRepository->update($id, $updateData);

        if ($updatedBlog) {
            // 处理标签关联
            if (array_key_exists('tags', $data)) {
                // 删除旧的标签关联
                $this->blogTagRelationRepository->deleteByBlogId($id);

                // 添加新的标签关联
                if (! empty($data['tags'])) {
                    $this->saveBlogTags($id, $data['tags']);
                }
            }

            // 记录日志
            $this->logger->info('更新博客成功: ' . $id . ' - ' . $updatedBlog['title']);

            // 清除相关缓存
            $this->clearBlogCache($id);
        }

        return $updatedBlog;
    }

    /**
     * 删除博客.
     * @param int $id 博客ID
     */
    public function deleteBlog(int $id): bool
    {
        // 使用Repository删除博客
        $result = $this->blogRepository->delete($id);

        if ($result) {
            // 记录日志
            $this->logger->info('删除博客成功: ' . $id);

            // 清除相关缓存
            $this->clearBlogCache($id);
        }

        return $result;
    }

    /**
     * 获取博客分类列表.
     */
    public function getCategories(): array
    {
        // 构建缓存键
        $cacheKey = 'blog:categories';

        // 尝试从缓存获取
        $cachedCategories = $this->cache->get($cacheKey);
        if ($cachedCategories) {
            return $cachedCategories;
        }

        // 使用Repository获取分类列表
        $categories = $this->blogCategoryRepository->findAll();

        // 缓存分类列表，设置30分钟过期
        $this->cache->set($cacheKey, $categories, 1800);

        return $categories;
    }

    /**
     * 记录博客阅读量.
     * @param int $blogId 博客ID
     * @param string $clientIp 客户端IP
     * @return false|int 更新后的阅读量或false(博客不存在)
     */
    public function recordView(int $blogId, string $clientIp): false|int
    {
        // 获取博客信息
        $blog = $this->getBlogById($blogId, false);
        if (! $blog) {
            return false;
        }

        // 增加阅读量
        $this->incrementBlogViewCount($blogId);

        // 获取更新后的博客信息
        $updatedBlog = $this->getBlogById($blogId, false);

        // 记录阅读日志
        $this->logger->info('博客阅读记录', [
            'blog_id' => $blogId,
            'client_ip' => $clientIp,
            'view_count' => $updatedBlog['view_count'],
        ]);

        return $updatedBlog['view_count'];
    }

    /**
     * 获取热门博客.
     * @param int $limit 数量限制
     */
    public function getHotBlogs(int $limit = 10): array
    {
        // 构建缓存键
        $cacheKey = 'blog:hot:' . $limit;

        // 尝试从缓存获取
        $cachedBlogs = $this->cache->get($cacheKey);
        if ($cachedBlogs) {
            return $cachedBlogs;
        }

        // 使用Repository获取热门博客
        $blogs = $this->blogRepository->getHotBlogs($limit);

        // 为每个热门博客获取标签信息
        foreach ($blogs as &$blog) {
            $blog['tags'] = $this->getBlogTags($blog['id']);
        }

        // 缓存热门博客列表，设置15分钟过期
        $this->cache->set($cacheKey, $blogs, 900);

        return $blogs;
    }

    /**
     * 获取推荐博客.
     * @param int $limit 数量限制
     */
    public function getRecommendedBlogs(int $limit = 10): array
    {
        // 构建缓存键
        $cacheKey = 'blog:recommended:' . $limit;

        // 尝试从缓存获取
        $cachedBlogs = $this->cache->get($cacheKey);
        if ($cachedBlogs) {
            return $cachedBlogs;
        }

        // 使用Repository获取推荐博客
        $blogs = $this->blogRepository->getRecommendedBlogs($limit);

        // 为每个推荐博客获取标签信息
        foreach ($blogs as &$blog) {
            $blog['tags'] = $this->getBlogTags($blog['id']);
        }

        // 缓存推荐博客列表，设置15分钟过期
        $this->cache->set($cacheKey, $blogs, 900);

        return $blogs;
    }

    /**
     * 搜索博客.
     * @param string $keyword 搜索关键词
     * @param array $params 其他参数
     */
    public function searchBlogs(string $keyword, array $params): array
    {
        // 使用Repository搜索博客
        $result = $this->blogRepository->search($keyword, $params);

        // 为每个博客获取标签信息
        foreach ($result['data'] as &$blog) {
            $blog['tags'] = $this->getBlogTags($blog['id']);
        }

        return $result;
    }

    /**
     * 增加博客阅读量.
     * @param int $id 博客ID
     */
    protected function incrementBlogViewCount(int $id): void
    {
        try {
            // 使用Redis原子操作增加阅读量，避免数据库锁竞争
            $viewCountKey = 'blog:view_count:' . $id;
            $this->cache->incr($viewCountKey);

            // 设置过期时间，确保数据最终一致性
            $this->cache->expire($viewCountKey, 86400); // 24小时过期

            // 使用异步任务更新数据库阅读量，减轻实时压力
            $this->taskService->dispatchTask(
                'App\Task\AsyncUpdateBlogViewTask',
                ['blog_id' => $id, 'retry_count' => 0, 'max_retries' => 3],
                false
            );
        } catch (Exception $e) {
            $this->logger->error('增加博客阅读量失败', ['blog_id' => $id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * 保存博客标签关联.
     *
     * @param int $blogId 博客ID
     * @param array $tags 标签数组
     */
    protected function saveBlogTags(int $blogId, array $tags): void
    {
        $tagPivotData = [];
        $order = 0;

        foreach ($tags as $tag) {
            // 获取或创建标签
            $tagId = $this->blogTagRepository->getOrCreateTag($tag['name'], $tag['slug'] ?? null);

            $tagPivotData[] = [
                'blog_id' => $blogId,
                'tag_id' => $tagId,
                'order' => $order++,
            ];
        }

        if (! empty($tagPivotData)) {
            $this->blogTagRelationRepository->batchCreate($tagPivotData);
        }
    }

    /**
     * 获取博客标签.
     *
     * @param int $blogId 博客ID
     * @return array 标签数组
     */
    protected function getBlogTags(int $blogId): array
    {
        return $this->blogTagRepository->getBlogTags($blogId);
    }

    /**
     * 清除博客相关缓存.
     * @param null|int $blogId 博客ID（可选，为null时清除所有列表缓存）
     */
    protected function clearBlogCache(?int $blogId = null): void
    {
        try {
            // 清除指定博客的详情缓存
            if ($blogId) {
                $this->cache->delete('blog:detail:' . $blogId);
                $this->cache->delete('blog:view_count:' . $blogId);
            }

            // 使用模式匹配删除所有博客列表相关缓存
            $keys = $this->cache->getRedis()->keys('blog:list:*');
            if (! empty($keys)) {
                $this->cache->getRedis()->del($keys);
            }

            // 清除热门和推荐博客缓存
            $this->cache->getRedis()->del(
                $this->cache->getRedis()->keys('blog:hot:*'),
                $this->cache->getRedis()->keys('blog:recommended:*')
            );
        } catch (Exception $e) {
            $this->logger->error('清除博客缓存失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 生成博客摘要
     * @param string $content 博客内容
     * @param int $length 摘要长度
     */
    protected function generateSummary(string $content, int $length = 200): string
    {
        // 去除HTML标签
        $text = strip_tags($content);
        // 去除多余的空白字符
        $text = preg_replace('/\s+/', ' ', $text);
        // 截取指定长度
        return substr($text, 0, $length) . (strlen($text) > $length ? '...' : '');
    }

    /**
     * 生成博客slug.
     * @param string $title 博客标题
     * @param int $excludeId 排除的ID（用于更新时避免冲突）
     */
    protected function generateSlug(string $title, int $excludeId = 0): string
    {
        // 转换为小写，替换非字母数字字符为连字符
        $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower($title));
        // 去除连续的连字符
        $slug = preg_replace('/-+/', '-', $slug);
        // 去除首尾连字符
        $slug = trim($slug, '-');

        // 使用Repository检查slug是否已存在
        $count = 1;
        $originalSlug = $slug;

        while ($this->blogRepository->checkSlugExists($slug, $excludeId)) {
            $slug = $originalSlug . '-' . $count++;
        }

        return $slug;
    }
}
