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

use Hyperf\DbConnection\Model\Relations\BelongsToMany;

/**
 * 博客标签模型.
 */
class BlogTag extends Model
{
    /**
     * 表名.
     */
    protected ?string $table = 'blog_tags';

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
    ];

    /**
     * 获取标签关联的博客
     * @return BelongsToMany
     */
    public function blogs()
    {
        return $this->belongsToMany(
            Blog::class,
            'blog_tag_relations',
            'tag_id',
            'blog_id'
        );
    }
}

