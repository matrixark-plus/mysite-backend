<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Comment;
use Hyperf\DbConnection\Db;

/**
 * 作品模型
 */
class Work extends Model
{
    /**
     * 表名
     * @var ?string
     */
    protected ?string $table = 'works';

    /**
     * 主键
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * 状态常量
     */
    const STATUS_DRAFT = 0;
    const STATUS_PUBLISHED = 1;
    const STATUS_HIDDEN = 2;

    /**
     * 可填充字段
     * @var array
     */
    protected array $fillable = [
        'title',
        'slug',
        'description',
        'content',
        'cover_image',
        'thumbnail',
        'link',
        'type',
        'author_id',
        'status',
        'is_recommended',
        'view_count',
        'like_count',
        'download_count',
        'created_at',
        'updated_at',
        'published_at',
    ];

    /**
     * 隐藏字段
     * @var array
     */
    protected array $hidden = [];

    /**
     * 时间戳字段
     * @var array
     */
    protected array $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'published_at' => 'timestamp',
        'status' => 'integer',
        'is_recommended' => 'boolean',
        'view_count' => 'integer',
        'like_count' => 'integer',
        'download_count' => 'integer',
    ];

    /**
     * 获取作品作者
     * @return \Hyperf\Model\Relations\BelongsTo
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id', 'id');
    }

    /**
     * 获取作品评论
     * @return \Hyperf\Database\Model\Collection
     */
    public function getComments($params = [])
    {
        $query = Comment::with('user:id,username,avatar')
            ->where('post_id', $this->id)
            ->where('post_type', Comment::POST_TYPE_WORK)
            ->approved(); // 使用作用域方法获取已审核通过的评论
        
        // 排序
        $query->orderBy('created_at', 'desc');
        
        return $query->get();
    }
    
    /**
     * 评论关联
     * @return \Hyperf\Model\Relations\HasMany
     */
    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id', 'id')
            ->where('post_type', Comment::POST_TYPE_WORK);
    }

    /**
     * 获取作品文件
     * @return \Hyperf\Model\Relations\HasMany
     */
    public function files()
    {
        return $this->hasMany(WorkFile::class, 'work_id', 'id');
    }
}