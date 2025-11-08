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

use Hyperf\Database\Model\Collection;
use Hyperf\Model\Relations\BelongsTo;
use Hyperf\Model\Relations\HasMany;

/**
 * 笔记模型.
 */
class Note extends Model
{
    /**
     * 状态常量.
     */
    public const STATUS_DRAFT = 0;      // 草稿

    public const STATUS_PUBLISHED = 1;  // 已发布

    public const STATUS_ARCHIVED = 2;   // 已归档

    /**
     * 可见性常量.
     */
    public const VISIBILITY_PRIVATE = 0;   // 私有

    public const VISIBILITY_PUBLIC = 1;    // 公开

    public const VISIBILITY_SHARED = 2;    // 共享

    /**
     * 时间戳字段.
     */
    public bool $timestamps = true;

    /**
     * 表名.
     */
    protected ?string $table = 'notes';

    /**
     * 主键.
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字段.
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
     * 隐藏字段.
     */
    protected array $hidden = [];

    /**
     * 获取笔记创建者.
     * @return BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }

    /**
     * 获取笔记版本历史.
     * @return Collection
     */
    public function getVersions()
    {
        return $this->versions;
    }

    /**
     * 笔记版本历史关联.
     * @return HasMany
     */
    public function versions()
    {
        return $this->hasMany(NoteVersion::class, 'note_id', 'id')
            ->orderBy('version_number', 'desc');
    }

    /**
     * 获取笔记的标签列表.
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
     * 设置笔记的标签列表.
     * @param array $tags 标签数组
     */
    public function setTags(array $tags)
    {
        $this->tags = json_encode($tags, JSON_UNESCAPED_UNICODE);
    }
}
