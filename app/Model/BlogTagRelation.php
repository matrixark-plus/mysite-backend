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

/**
 * 博客标签关联模型.
 */
class BlogTagRelation extends Model
{
    /**
     * 表名.
     */
    protected ?string $table = 'blog_tag_relations';

    /**
     * 主键.
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字段
     */
    protected array $fillable = [
        'blog_id',
        'tag_id',
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
        'blog_id' => 'integer',
        'tag_id' => 'integer',
    ];

    /**
     * 获取关联博客.
     * @return BelongsTo
     */
    public function blog()
    {
        return $this->belongsTo(Blog::class, 'blog_id', 'id');
    }

    /**
     * 获取关联标签.
     * @return BelongsTo
     */
    public function tag()
    {
        return $this->belongsTo(BlogTag::class, 'tag_id', 'id');
    }
}

