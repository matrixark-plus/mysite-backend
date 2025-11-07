<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Db;

/**
 * 笔记模型
 */
class Note extends Model
{
    /**
     * 表名
     * @var ?string
     */
    protected ?string $table = 'notes';

    /**
     * 主键
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * 状态常量
     */
    const STATUS_DRAFT = 0;      // 草稿
    const STATUS_PUBLISHED = 1;  // 已发布
    const STATUS_ARCHIVED = 2;   // 已归档
    
    /**
     * 可见性常量
     */
    const VISIBILITY_PRIVATE = 0;   // 私有
    const VISIBILITY_PUBLIC = 1;    // 公开
    const VISIBILITY_SHARED = 2;    // 共享

    /**
     * 可填充字段
     * @var array
     */
    protected array $fillable = [
        'title',
        'content',
        'excerpt',
        'creator_id',
        'status',
        'is_public',
        'tags',
    ];

    /**
     * 隐藏字段
     * @var array
     */
    protected array $hidden = [];

    /**
     * 时间戳字段
     * @var bool
     */
    public bool $timestamps = true;
    
    /**
     * 获取笔记创建者
     * @return \Hyperf\Model\Relations\BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }
    
    /**
     * 获取笔记版本历史
     * @return \Hyperf\Database\Model\Collection
     */
    public function getVersions()
    {
        return $this->versions;
    }
    
    /**
     * 笔记版本历史关联
     * @return \Hyperf\Model\Relations\HasMany
     */
    public function versions()
    {
        return $this->hasMany(NoteVersion::class, 'note_id', 'id')
            ->orderBy('version_number', 'desc');
    }
    
    /**
     * 获取笔记的标签列表
     * @return array
     */
    public function getTags()
    {
        if (empty($this->tags)) {
            return [];
        }
        return json_decode($this->tags, true) ?: [];
    }
    
    /**
     * 设置笔记的标签列表
     * @param array $tags 标签数组
     */
    public function setTags(array $tags)
    {
        $this->tags = json_encode($tags, JSON_UNESCAPED_UNICODE);
    }
}