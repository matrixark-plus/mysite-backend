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

namespace App\Model;

use Hyperf\Model\Relations\BelongsTo;
use Hyperf\Model\Relations\HasMany;

/**
 * 博客分类模型.
 */
class BlogCategory extends Model
{
    /**
     * 表名.
     */
    protected ?string $table = 'blog_categories';

    /**
     * 主键.
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字段.
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
     * 隐藏字段.
     */
    protected array $hidden = [];

    /**
     * 时间戳字段.
     */
    protected array $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'sort_order' => 'integer',
        'status' => 'boolean',
    ];

    /**
     * 获取分类下的博客.
     * @return HasMany
     */
    public function blogs()
    {
        return $this->hasMany(Blog::class, 'category_id', 'id');
    }

    /**
     * 获取父分类.
     * @return BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(BlogCategory::class, 'parent_id', 'id');
    }

    /**
     * 获取子分类.
     * @return HasMany
     */
    public function children()
    {
        return $this->hasMany(BlogCategory::class, 'parent_id', 'id');
    }
}
