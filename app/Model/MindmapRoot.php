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
 * 脑图根节点模型
 */
class MindmapRoot extends Model
{
    /**
     * 表名.
     */
    protected ?string $table = 'mindmap_roots';

    /**
     * 主键.
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字�?
     */
    protected array $fillable = [
        'title',
        'description',
        'screenshot_path',
        'creator_id',
        'is_public',
    ];

    /**
     * 隐藏字段.
     */
    protected array $hidden = [];

    /**
     * 时间戳字�?
     */
    protected array $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'is_public' => 'boolean',
    ];

    /**
     * 获取创建�?
     * @return BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }

    /**
     * 获取所有节�?
     * @return HasMany
     */
    public function nodes()
    {
        return $this->hasMany(MindmapNode::class, 'root_id', 'id');
    }
}

