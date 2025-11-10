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

use App\Model\BlogCategory;

class BlogCategoryRepository
{
    /**
     * 获取所有分类.
     * @return array 分类列表
     */
    public function findAll(): array
    {
        return BlogCategory::orderBy('sort_order', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * 根据ID获取分类.
     * @param int $id 分类ID
     * @return null|array 分类信息
     */
    public function findById(int $id): ?array
    {
        $category = BlogCategory::find($id);
        return $category ? $category->toArray() : null;
    }

    /**
     * 创建分类.
     * @param array $data 分类数据
     * @return array 创建的分类信息
     */
    public function create(array $data): array
    {
        $category = BlogCategory::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? $this->generateSlug($data['name']),
            'parent_id' => $data['parent_id'] ?? 0,
            'description' => $data['description'] ?? '',
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => $data['status'] ?? true,
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        return $category->toArray();
    }

    /**
     * 更新分类.
     * @param int $id 分类ID
     * @param array $data 更新数据
     * @return null|array 更新后的分类信息
     */
    public function update(int $id, array $data): ?array
    {
        $category = BlogCategory::find($id);
        if (! $category) {
            return null;
        }

        $updateData = $data;
        if (! isset($data['updated_at'])) {
            $updateData['updated_at'] = time();
        }

        $category->update($updateData);
        return $category->toArray();
    }

    /**
     * 删除分类.
     * @param int $id 分类ID
     * @return bool 删除结果
     */
    public function delete(int $id): bool
    {
        return BlogCategory::destroy($id) > 0;
    }

    /**
     * 获取分类下的博客数量.
     * @param int $categoryId 分类ID
     * @return int 博客数量
     */
    public function getBlogCount(int $categoryId): int
    {
        return BlogCategory::find($categoryId)->blogs->count();
    }

    /**
     * 获取父分类下的子分类.
     * @param int $parentId 父分类ID
     * @return array 子分类列表
     */
    public function findChildren(int $parentId): array
    {
        return BlogCategory::where('parent_id', $parentId)
            ->orderBy('sort_order', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * 检查分类名称是否已存在.
     * @param string $name 分类名称
     * @param int $excludeId 排除的ID
     * @return bool 是否存在
     */
    public function checkNameExists(string $name, int $excludeId = 0): bool
    {
        $query = BlogCategory::where('name', $name);
        if ($excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * 检查分类slug是否已存在.
     * @param string $slug slug
     * @param int $excludeId 排除的ID
     * @return bool 是否存在
     */
    public function checkSlugExists(string $slug, int $excludeId = 0): bool
    {
        $query = BlogCategory::where('slug', $slug);
        if ($excludeId > 0) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * 生成分类slug.
     * @param string $name 分类名称
     * @param int $excludeId 排除的ID
     * @return string 生成的slug
     */
    protected function generateSlug(string $name, int $excludeId = 0): string
    {
        // 转换为小写，替换非字母数字字符为连字符
        $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower($name));
        // 去除连续的连字符
        $slug = preg_replace('/-+/', '-', $slug);
        // 去除首尾连字符
        $slug = trim($slug, '-');

        // 检查slug是否已存在
        $count = 1;
        $originalSlug = $slug;

        while ($this->checkSlugExists($slug, $excludeId)) {
            $slug = $originalSlug . '-' . $count++;
        }

        return $slug;
    }
}
