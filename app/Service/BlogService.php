<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Blog;
use App\Model\BlogCategory;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Arr;

class BlogService
{
    /**
     * 获取博客列表
     * @param array $params 查询参数
     * @return array
     */
    public function getBlogs(array $params): array
    {
        $query = Blog::query()
            ->with(['author', 'category'])
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
            'data' => $blogs->items()
        ];
    }

    /**
     * 根据ID获取博客详情
     * @param int $id 博客ID
     * @return Blog|null
     */
    public function getBlogById(int $id): ?Blog
    {
        $blog = Blog::with(['author', 'category'])
            ->where('id', $id)
            ->where('status', Blog::STATUS_PUBLISHED)
            ->first();

        if ($blog) {
            // 增加浏览量
            $blog->increment('view_count');
        }

        return $blog;
    }

    /**
     * 创建博客
     * @param array $data 博客数据
     * @return Blog
     */
    public function createBlog(array $data): Blog
    {
        return Db::transaction(function () use ($data) {
            // 创建博客
            $blog = Blog::create([
                'title' => $data['title'],
                'slug' => $this->generateSlug($data['title']),
                'content' => $data['content'],
                'summary' => $data['summary'] ?? $this->generateSummary($data['content']),
                'category_id' => $data['category_id'],
                'author_id' => $data['author_id'],
                'status' => $data['status'] ?? Blog::STATUS_PUBLISHED,
                'is_recommended' => $data['is_recommended'] ?? false,
                'cover_image' => $data['cover_image'] ?? '',
                'created_at' => time(),
                'updated_at' => time(),
            ]);

            // 记录日志
            logger()->info('创建博客成功: ' . $blog->id . ' - ' . $blog->title);

            return $blog;
        });
    }

    /**
     * 更新博客
     * @param int $id 博客ID
     * @param array $data 更新数据
     * @return Blog|null
     */
    public function updateBlog(int $id, array $data): ?Blog
    {
        $blog = Blog::find($id);
        if (!$blog) {
            return null;
        }

        return Db::transaction(function () use ($blog, $data) {
            // 更新博客
            $updateData = [];
            if (isset($data['title'])) {
                $updateData['title'] = $data['title'];
                $updateData['slug'] = $this->generateSlug($data['title'], $blog->id);
            }
            if (isset($data['content'])) {
                $updateData['content'] = $data['content'];
                // 如果没有提供摘要，重新生成
                if (!isset($data['summary'])) {
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
            $updateData['updated_at'] = time();

            $blog->update($updateData);

            // 记录日志
            logger()->info('更新博客成功: ' . $blog->id . ' - ' . $blog->title);

            return $blog;
        });
    }

    /**
     * 删除博客
     * @param int $id 博客ID
     * @return bool
     */
    public function deleteBlog(int $id): bool
    {
        $blog = Blog::find($id);
        if (!$blog) {
            return false;
        }

        return Db::transaction(function () use ($blog) {
            // 删除博客
            $blog->delete();

            // 记录日志
            logger()->info('删除博客成功: ' . $blog->id . ' - ' . $blog->title);

            return true;
        });
    }

    /**
     * 获取博客分类列表
     * @return array
     */
    public function getCategories(): array
    {
        return BlogCategory::orderBy('sort_order', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * 获取热门博客
     * @param int $limit 数量限制
     * @return array
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
     * 获取推荐博客
     * @param int $limit 数量限制
     * @return array
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
     * 搜索博客
     * @param string $keyword 搜索关键词
     * @param array $params 其他参数
     * @return array
     */
    public function searchBlogs(string $keyword, array $params): array
    {
        $query = Blog::query()
            ->with(['author', 'category'])
            ->where('status', Blog::STATUS_PUBLISHED);

        // 关键词搜索
        $query->where(function ($q) use ($keyword) {
            $q->where('title', 'like', '%' . $keyword . '%')
              ->orWhere('content', 'like', '%' . $keyword . '%')
              ->orWhere('summary', 'like', '%' . $keyword . '%');
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
            'data' => $blogs->items()
        ];
    }



    /**
     * 生成博客摘要
     * @param string $content 博客内容
     * @param int $length 摘要长度
     * @return string
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
     * 生成博客slug
     * @param string $title 博客标题
     * @param int $excludeId 排除的ID（用于更新时避免冲突）
     * @return string
     */
    protected function generateSlug(string $title, int $excludeId = 0): string
    {
        // 转换为小写，替换非字母数字字符为连字符
        $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower($title));
        // 去除连续的连字符
        $slug = preg_replace('/-+/', '-', $slug);
        // 去除首尾连字符
        $slug = trim($slug, '-');

        // 检查slug是否已存在
        $query = Blog::where('slug', $slug);
        if ($excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }

        $count = $query->count();
        if ($count > 0) {
            $slug .= '-' . $count;
        }

        return $slug;
    }
}