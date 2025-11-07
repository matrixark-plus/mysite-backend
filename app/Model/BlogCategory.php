<?php

declare(strict_types=1);

namespace App\Model;

/**
 * 博客分类模型
 */
class BlogCategory extends Model
{
    /**
     * 表名
     * @var ?string
     */
    protected ?string $table = 'blog_categories';

    /**
     * 主键
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字段
     * @var array
     */
    protected array $fillable = [
        'name',
        'slug',
        'parent_id',
        'description',
        'sort_order',
        'status',
        'created_at',
        'updated_at',
    ];

    /**
     * 隐藏字段
     * @var array
     */
    protected array $hidden = [];

    /**
     * 时间戳字段
     * @var array
     */
    protected array $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'sort_order' => 'integer',
        'status' => 'boolean',
    ];

    /**
     * 获取分类下的博客
     * @return \Hyperf\Model\Relations\HasMany
     */
    public function blogs()
    {
        return $this->hasMany(Blog::class, 'category_id', 'id');
    }

    /**
     * 获取父分类
     * @return \Hyperf\Model\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(BlogCategory::class, 'parent_id', 'id');
    }

    /**
     * 获取子分类
     * @return \Hyperf\Model\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(BlogCategory::class, 'parent_id', 'id');
    }
}