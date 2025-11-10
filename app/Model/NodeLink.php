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
 * 节点链接模型.
 */
class NodeLink extends Model
{
    /**
     * 链接类型常量.
     */
    public const LINK_TYPE_BIDIRECTIONAL = 'bidirectional';

    public const LINK_TYPE_UNIDIRECTIONAL = 'unidirectional';

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
     * 隐藏字段.
     */
    protected array $hidden = [];

    /**
     * 时间戳字�?
     */
    protected array $casts = [
        'created_at' => 'timestamp',
    ];

    /**
     * 获取源节�?
     * @return BelongsTo
     */
    public function sourceNode()
    {
        return $this->belongsTo(MindmapNode::class, 'source_node_id', 'id');
    }

    /**
     * 获取目标节点.
     * @return BelongsTo
     */
    public function targetNode()
    {
        return $this->belongsTo(MindmapNode::class, 'target_node_id', 'id');
    }
}

