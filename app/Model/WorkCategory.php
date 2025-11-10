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

use Hyperf\DbConnection\Model\Relations\BelongsTo;
use Hyperf\DbConnection\Model\Relations\HasMany;

/**
 * 作品分类模型.
 */
class WorkCategory extends Model
{
    /**
     * 表名.
     */
    protected ?string $table = 'work_categories';

    /**
     * 主键.
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字段
     */
    protected array $fillable = [
        'name',
        'slug',
        'parent_id',
        'description',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    /**
     * 隐藏字段.
     */
    protected array $hidden = [];

    /**
     * 时间戳字段
     */
    protected array $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'parent_id' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * 获取父分类
     * @return BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(WorkCategory::class, 'parent_id', 'id');
    }

    /**
     * 获取子分类
     * @return HasMany
     */
    public function children()
    {
        return $this->hasMany(WorkCategory::class, 'parent_id', 'id');
    }

    /**
     * 获取分类下的作品.
     * @return HasMany
     */
    public function works()
    {
        return $this->hasMany(Work::class, 'category_id', 'id');
    }
}

