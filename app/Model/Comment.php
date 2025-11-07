<?php

namespace App\Model;

use Hyperf\DbConnection\Db;

/**
 * 评论模型
 * @package App\Model
 */
class Comment extends Model
{
    /**
     * 表名
     * @var ?string
     */
    protected ?string $table = 'comments';

    /**
     * 主键
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * 状态常量
     */
    const STATUS_PENDING = 0; // 待审核
    const STATUS_APPROVED = 1; // 已通过
    const STATUS_REJECTED = 2; // 已拒绝

    /**
     * 内容类型常量
     */
    const POST_TYPE_BLOG = 'blog';
    const POST_TYPE_WORK = 'work';

    /**
     * 可填充字段
     * @var array
     */
    protected array $fillable = [
        'user_id',
        'post_id',
        'post_type',
        'parent_id',
        'content',
        'status',
    ];

    /**
     * 时间戳
     * @var bool
     */
    public bool $timestamps = true;

    /**
     * 获取评论用户
     * @return \Hyperf\Model\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 获取父评论
     * @return \Hyperf\Model\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    /**
     * 获取子评论
     * @return \Hyperf\Model\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }

    /**
     * 获取关联的博客（多态关联）
     * @return \Hyperf\Model\Relations\MorphTo
     */
    public function blog()
    {
        return $this->morphTo(
            'post',
            'post_type',
            'post_id'
        )->where('post_type', self::POST_TYPE_BLOG);
    }

    /**
     * 获取关联的作品（多态关联）
     * @return \Hyperf\Model\Relations\MorphTo
     */
    public function work()
    {
        return $this->morphTo(
            'post',
            'post_type',
            'post_id'
        )->where('post_type', self::POST_TYPE_WORK);
    }

    /**
     * 获取状态文本
     * @return string
     */
    public function getStatusTextAttribute()
    {
        $statusMap = [
            self::STATUS_PENDING => '待审核',
            self::STATUS_APPROVED => '已通过',
            self::STATUS_REJECTED => '已拒绝',
        ];
        return $statusMap[$this->status] ?? '未知';
    }

    /**
     * 范围：仅获取已审核通过的评论
     * @param \Hyperf\Database\Query\Builder $query
     * @return \Hyperf\Database\Query\Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * 范围：按类型筛选
     * @param \Hyperf\Database\Query\Builder $query
     * @param string $type
     * @return \Hyperf\Database\Query\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('post_type', $type);
    }
}