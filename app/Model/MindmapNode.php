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
 * 脑图节点模型.
 */
class MindmapNode extends Model
{
    /**
     * 节点类型常量.
     */
    public const NODE_TYPE_NORMAL = 'node';
    public const NODE_TYPE_NOTE_LINK = 'note_link';

    /**
     * 表名.
     */
    protected ?string $table = 'mindmap_nodes';

    /**
     * 主键.
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字段.
     */
    protected array $fillable = [
        'root_id',
        'parent_id',
        'title',
        'node_type',
        'note_id',
        'position_x',
        'position_y',
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
        'position_x' => 'float',
        'position_y' => 'float',
    ];

    /**
     * 获取根节点
     * @return BelongsTo
     */
    public function root()
    {
        return $this->belongsTo(MindmapRoot::class, 'root_id', 'id');
    }

    /**
     * 获取父节点
     * @return BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(MindmapNode::class, 'parent_id', 'id');
    }

    /**
     * 获取子节点
     * @return HasMany
     */
    public function children()
    {
        return $this->hasMany(MindmapNode::class, 'parent_id', 'id');
    }

    /**
     * 获取关联笔记
     * @return BelongsTo
     */
    public function note()
    {
        return $this->belongsTo(Note::class, 'note_id', 'id');
    }
}