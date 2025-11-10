<?php

declare(strict_types=1);
/**
 * 评论点赞模型
 */

namespace App\Model;

use App\Model\Comment;
use App\Model\User;
use Hyperf\DbConnection\Model\Model;
use Hyperf\Database\Model\Relations\BelongsTo;

/**
 * 评论点赞模型.
 */
class CommentLike extends Model
{
    /**
     * 表名.
     */
    protected ?string $table = 'comment_likes';

    /**
     * 主键.
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字段
     */
    protected array $fillable = [
        'comment_id',
        'user_id',
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
     * 获取关联的评论
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'comment_id', 'id');
    }

    /**
     * 获取点赞的用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // 注：业务逻辑方法已移至CommentLikeService层
    // 保留纯模型关系定义
}

