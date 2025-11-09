<?php

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * 节点链接表模型
 * 用于表示脑图节点之间的链接关系
 */
class NodeLinks extends Model
{
    /**
     * 表名
     * @var string
     */
    protected $table = 'node_links';

    /**
     * 可填充字段
     * @var array
     */
    protected $fillable = [
        'source_node_id',
        'target_node_id',
        'link_type',
        'label',
    ];

    /**
     * 时间戳
     * @var bool
     */
    public $timestamps = true;

    /**
     * 链接类型常量
     */
    const LINK_TYPE_BIDIRECTIONAL = 'bidirectional';
    const LINK_TYPE_UNIDIRECTIONAL = 'unidirectional';

    /**
     * 获取源节点
     * @return \Hyperf\Database\Eloquent\Relations\BelongsTo
     */
    public function sourceNode()
    {
        return $this->belongsTo(MindmapNodes::class, 'source_node_id', 'id');
    }

    /**
     * 获取目标节点
     * @return \Hyperf\Database\Eloquent\Relations\BelongsTo
     */
    public function targetNode()
    {
        return $this->belongsTo(MindmapNodes::class, 'target_node_id', 'id');
    }
}