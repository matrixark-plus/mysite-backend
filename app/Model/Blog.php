<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Comment;

/**
 * 博客模型
 */
class Blog extends Model
{
    /**
     * 表名
     * @var ?string
     */
    protected ?string $table = 'blogs';

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
        'excerpt',
        'content',
        'category_id',
        'author_id',
        'status',
        'cover_image',
        'view_count',
        'comment_count',
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
        'comment_count' => 'integer',
    ];

    /**
     * 获取博客作者
     * @return \Hyperf\Model\Relations\BelongsTo
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id', 'id');
    }

    /**
     * 获取博客分类
     * @return \Hyperf\Model\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'category_id', 'id');
    }

    /**
     * 获取博客评论
     * @return \Hyperf\Database\Model\Collection
     */
    public function getComments($params = [])
    {
        $query = Comment::with('user:id,username,avatar')
            ->where('post_id', $this->id)
            ->where('post_type', Comment::POST_TYPE_BLOG)
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
            ->where('post_type', Comment::POST_TYPE_BLOG);
    }

    /**
     * 获取博客标签
     * @return \Hyperf\Model\Relations\BelongsToMany
     */
    public function tags()
    {
        return $this->belongsToMany(
            BlogTag::class,
            'blog_tag_pivot',
            'blog_id',
            'tag_id'
        );
    }
}