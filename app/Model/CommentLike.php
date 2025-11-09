<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Model\Relations\BelongsTo;

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
     * 可填充字段.
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
     * 时间戳字段.
     */
    protected array $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * 获取关联的评论.
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'comment_id', 'id');
    }

    /**
     * 获取点赞的用户.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 检查用户是否已点赞
     * @param int $commentId 评论ID
     * @param int $userId 用户ID
     * @return bool
     */
    public static function isLiked(int $commentId, int $userId): bool
    {
        return self::where('comment_id', $commentId)
            ->where('user_id', $userId)
            ->first() !== null;
    }

    /**
     * 获取评论点赞数
     * @param int $commentId 评论ID
     * @return int
     */
    public static function getLikeCount(int $commentId): int
    {
        return self::where('comment_id', $commentId)
            ->count();
    }
}