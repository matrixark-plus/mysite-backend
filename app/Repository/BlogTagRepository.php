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

use Hyperf\Di\Annotation\Inject;
use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;

/**
 * 博客标签数据访问层
 * 封装所有与博客标签数据相关的数据库操作.
 */
class BlogTagRepository
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 获取或创建标签
     *
     * @param string $name 标签名称
     * @param string|null $slug 标签别名
     * @return int 标签ID
     */
    public function getOrCreateTag(string $name, ?string $slug = null): int
    {        
        try {
            // 查找现有标签
            $existingTag = Db::table('blog_tags')
                ->where('name', $name)
                ->orWhere(function ($query) use ($slug) {
                    if ($slug) {
                        $query->where('slug', $slug);
                    }
                })
                ->first();

            if ($existingTag) {
                return $existingTag->id;
            }

            // 创建新标签
            $tagSlug = $slug ?? $this->generateSlug($name);
            return Db::table('blog_tags')->insertGetId([
                'name' => $name,
                'slug' => $tagSlug,
                'created_at' => time(),
                'updated_at' => time(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取或创建标签失败: ' . $e->getMessage(), [
                'name' => $name,
                'slug' => $slug
            ]);
            // 再次尝试查找标签，以防并发创建
            $existingTag = Db::table('blog_tags')
                ->where('name', $name)
                ->first();
            
            if ($existingTag) {
                return $existingTag->id;
            }
            
            throw $e;
        }
    }

    /**
     * 生成标签slug
     *
     * @param string $name 标签名称
     * @return string slug
     */
    protected function generateSlug(string $name): string
    {        
        // 转换为小写，替换非字母数字字符为连字符
        $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower($name));
        // 去除连续的连字符
        $slug = preg_replace('/-+/', '-', $slug);
        // 去除首尾连字符
        $slug = trim($slug, '-');

        // 检查slug是否已存在
        $count = Db::table('blog_tags')
            ->where('slug', $slug)
            ->count();
            
        if ($count > 0) {
            $slug .= '-' . ($count + 1);
        }

        return $slug;
    }

    /**
     * 根据博客ID获取标签
     *
     * @param int $blogId 博客ID
     * @return array 标签数组
     */
    public function getBlogTags(int $blogId): array
    {        
        try {
            return Db::table('blog_tags')
                ->join('blog_tag_pivot', 'blog_tags.id', '=', 'blog_tag_pivot.tag_id')
                ->select('blog_tags.id', 'blog_tags.name', 'blog_tags.slug')
                ->where('blog_tag_pivot.blog_id', $blogId)
                ->orderBy('blog_tag_pivot.order', 'asc')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            $this->logger->error('获取博客标签失败: ' . $e->getMessage(), ['blog_id' => $blogId]);
            return [];
        }
    }

    /**
     * 根据ID获取标签
     *
     * @param int $id 标签ID
     * @return array|null 标签信息
     */
    public function findById(int $id): ?array
    {        
        try {
            $tag = Db::table('blog_tags')
                ->where('id', $id)
                ->first();
                
            return $tag ? (array) $tag : null;
        } catch (\Exception $e) {
            $this->logger->error('根据ID获取标签失败: ' . $e->getMessage(), ['tag_id' => $id]);
            return null;
        }
    }

    /**
     * 获取所有标签
     *
     * @return array 标签列表
     */
    public function findAll(): array
    {        
        try {
            return Db::table('blog_tags')
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            $this->logger->error('获取所有标签失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 根据ID数组获取标签
     *
     * @param array $ids 标签ID数组
     * @return array 标签列表
     */
    public function findByIds(array $ids): array
    {        
        try {
            return Db::table('blog_tags')
                ->whereIn('id', $ids)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            $this->logger->error('根据ID数组获取标签失败: ' . $e->getMessage(), ['tag_ids' => $ids]);
            return [];
        }
    }
}