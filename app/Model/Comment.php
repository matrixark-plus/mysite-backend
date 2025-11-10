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

use Hyperf\Database\Query\Builder;
use Hyperf\DbConnection\Model\Relations\BelongsTo;
use Hyperf\DbConnection\Model\Relations\HasMany;
use Hyperf\DbConnection\Model\Relations\MorphTo;

/**
 * 评论模型.
 */
class Comment extends Model
{
    /**
     * 状态常量
     */
    public const STATUS_PENDING = 0; // 待审核
    public const STATUS_APPROVED = 1; // 已通过

    public const STATUS_REJECTED = 2; // 已拒绝
    /**
     * 内容类型常量.
     */
    public const POST_TYPE_BLOG = 'blog';

    public const POST_TYPE_WORK = 'work';

    /**
     * 时间戳
     */
    public bool $timestamps = true;

    /**
     * 表名.
     */
    protected ?string $table = 'comments';

    /**
     * 主键.
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字段
     */
    protected array $fillable = [
        'user_id',
        'post_id',
        'post_type',
        'parent_id',
        'content',
        'status',
        'created_at',
        'updated_at',
    ];

    /**
     * 获取评论用户.
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 获取父评论
     * @return BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    /**
     * 获取子评论
     * @return HasMany
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }

    /**
     * 获取关联的博客（多态关联）.
     * @return MorphTo
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
     * 获取关联的作品（多态关联）.
     * @return MorphTo
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
     * @param Builder $query
     * @return Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * 范围：按类型筛选
     * @param Builder $query
     * @return Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('post_type', $type);
    }
}

