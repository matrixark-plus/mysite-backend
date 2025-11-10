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

use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\DbConnection\Model\Model;

/**
 * 节点链接表模�? * 用于表示脑图节点之间的链接关�?
 */
class NodeLinks extends Model
{
    /**
     * 链接类型常量.
     */
    public const LINK_TYPE_BIDIRECTIONAL = 'bidirectional';

    public const LINK_TYPE_UNIDIRECTIONAL = 'unidirectional';

    /**
     * 时间�?
     */
    public bool $timestamps = true;

    /**
     * 表名.
     */
    protected ?string $table = 'node_links';

    /**
     * 主键.
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字�?
     */
    protected array $fillable = [
        'source_node_id',
        'target_node_id',
        'link_type',
        'label',
    ];

    /**
     * 时间戳字�?
     */
    protected array $casts = [
        'created_at' => 'timestamp',
        'source_node_id' => 'integer',
        'target_node_id' => 'integer',
    ];

    /**
     * 获取源节�?
     */
    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(MindmapNode::class, 'source_node_id', 'id');
    }

    /**
     * 获取目标节点.
     */
    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(MindmapNode::class, 'target_node_id', 'id');
    }

    /**
     * 访问器：获取完整链接信息.
     */
    public function getFullLinkAttribute(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source_node_id,
            'target' => $this->target_node_id,
            'type' => $this->link_type,
            'label' => $this->label,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * 修改器：设置链接类型.
     */
    public function setLinkTypeAttribute(string $value): void
    {
        $validTypes = [self::LINK_TYPE_BIDIRECTIONAL, self::LINK_TYPE_UNIDIRECTIONAL];
        $this->attributes['link_type'] = in_array($value, $validTypes) ? $value : self::LINK_TYPE_UNIDIRECTIONAL;
    }
}

