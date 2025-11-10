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

use App\Model\Blog;
use Exception;
use Hyperf\DbConnection\Db;

class BlogRepository
{
    /**
     * 获取博客列表.
     * @param array $params 查询参数
     * @return array 分页结果
     */
    public function findAll(array $params): array
    {
        $query = Blog::query()
            ->with(['author', 'category', 'tags'])
            ->where('status', Blog::STATUS_PUBLISHED);

        // 分类筛选
        if (isset($params['category_id']) && $params['category_id']) {
            $query->where('category_id', $params['category_id']);
        }

        // 关键词搜索
        if (isset($params['keyword']) && $params['keyword']) {
            $keyword = $params['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', '%' . $keyword . '%')
                    ->orWhere('content', 'like', '%' . $keyword . '%');
            });
        }

        // 排序
        $sortBy = $params['sort_by'] ?? 'created_at';
        $sortOrder = $params['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // 分页
        $page = $params['page'] ?? 1;
        $pageSize = $params['page_size'] ?? 10;

        $blogs = $query->paginate($pageSize, ['*'], 'page', $page);

        return [
            'total' => $blogs->total(),
            'page' => $blogs->currentPage(),
            'page_size' => $blogs->perPage(),
            'data' => $blogs->items(),
        ];
    }

    /**
     * 根据ID获取博客详情.
     * @param int $id 博客ID
     * @return null|array 博客信息
     */
    public function findById(int $id): ?array
    {
        $blog = Blog::with(['author', 'category', 'tags'])
            ->where('id', $id)
            ->where('status', Blog::STATUS_PUBLISHED)
            ->first();

        return $blog ? $blog->toArray() : null;
    }

    /**
     * 创建博客.
     * @param array $data 博客数据
     * @return array 创建的博客信息
     */
    public function create(array $data): array
    {
        return Db::transaction(function () use ($data) {
            $blog = Blog::create([
                'title' => $data['title'],
                'slug' => $data['slug'],
                'content' => $data['content'],
                'summary' => $data['summary'] ?? '',
                'category_id' => $data['category_id'],
                'author_id' => $data['author_id'],
                'status' => $data['status'] ?? Blog::STATUS_PUBLISHED,
                'is_recommended' => $data['is_recommended'] ?? false,
                'cover_image' => $data['cover_image'] ?? '',
                'created_at' => time(),
                'updated_at' => time(),
            ]);

            return $blog->toArray();
        });
    }

    /**
     * 更新博客.
     * @param int $id 博客ID
     * @param array $data 更新数据
     * @return null|array 更新后的博客信息
     */
    public function update(int $id, array $data): ?array
    {
        return Db::transaction(function () use ($id, $data) {
            $blog = Blog::find($id);
            if (! $blog) {
                return null;
            }

            $blog->update($data);
            return $blog->toArray();
        });
    }

    /**
     * 删除博客.
     * @param int $id 博客ID
     * @return bool 删除结果
     */
    public function delete(int $id): bool
    {
        return Db::transaction(function () use ($id) {
            $blog = Blog::find($id);
            if (! $blog) {
                return false;
            }

            return $blog->delete();
        });
    }

    /**
     * 增加博客阅读量.
     * @param int $id 博客ID
     */
    public function incrementViewCount(int $id): void
    {
        Blog::where('id', $id)->increment('view_count');
    }

    /**
     * 获取热门博客.
     * @param int $limit 数量限制
     * @return array 热门博客列表
     */
    public function getHotBlogs(int $limit = 10): array
    {
        return Blog::with(['author', 'category'])
            ->where('status', Blog::STATUS_PUBLISHED)
            ->orderBy('view_count', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 获取推荐博客.
     * @param int $limit 数量限制
     * @return array 推荐博客列表
     */
    public function getRecommendedBlogs(int $limit = 10): array
    {
        return Blog::with(['author', 'category'])
            ->where('status', Blog::STATUS_PUBLISHED)
            ->where('is_recommended', true)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 搜索博客.
     * @param string $keyword 搜索关键词
     * @param array $params 其他参数
     * @return array 搜索结果
     */
    public function search(string $keyword, array $params): array
    {
        $query = Blog::query()
            ->with(['author', 'category'])
            ->where('status', Blog::STATUS_PUBLISHED);

        // 关键词搜索
        $query->where(function ($q) use ($keyword) {
            $q->where('title', 'like', '%' . $keyword . '%')
                ->orWhere('content', 'like', '%' . $keyword . '%')
                ->orWhere('summary', 'like', '%' . $keyword . '%')
                // 也可以搜索标签名称
                ->orWhereExists(function ($tagQuery) use ($keyword) {
                    $tagQuery->select(Db::raw(1))
                        ->from('blog_tag_pivot')
                        ->join('blog_tags', 'blog_tag_pivot.tag_id', '=', 'blog_tags.id')
                        ->whereRaw('blog_tag_pivot.blog_id = blogs.id')
                        ->where('blog_tags.name', 'like', '%' . $keyword . '%');
                });
        });

        // 排序
        $sortBy = $params['sort_by'] ?? 'created_at';
        $sortOrder = $params['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // 分页
        $page = $params['page'] ?? 1;
        $pageSize = $params['page_size'] ?? 10;

        $blogs = $query->paginate($pageSize, ['*'], 'page', $page);

        return [
            'total' => $blogs->total(),
            'page' => $blogs->currentPage(),
            'page_size' => $blogs->perPage(),
            'data' => $blogs->items(),
        ];
    }

    /**
     * 检查slug是否已存在.
     * @param string $slug slug
     * @param int $excludeId 排除的ID
     * @return bool 是否存在
     */
    public function checkSlugExists(string $slug, int $excludeId = 0): bool
    {
        $query = Blog::where('slug', $slug);
        if ($excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * 获取博客数量.
     * @param array $conditions 条件
     * @return int 数量
     */
    public function count(array $conditions = []): int
    {
        $query = Blog::query();

        if (! empty($conditions)) {
            foreach ($conditions as $key => $value) {
                $query->where($key, $value);
            }
        }

        return $query->count();
    }
    
    /**
     * 更新博客评论计数.
     * @param int $id 博客ID
     * @param int $count 评论数量
     * @return bool 更新结果
     */
    public function updateCommentCount(int $id, int $count): bool
    {
        try {
            return Blog::where('id', $id)->update(['comment_count' => $count]) > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}
