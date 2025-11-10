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
use Hyperf\DbConnection\Model\Relations\BelongsTo;
use Hyperf\DbConnection\Model\Relations\HasMany;

/**
 * 笔记模型.
 */
class Note extends Model
{
    /**
     * 状态常�?
     */
    public const STATUS_DRAFT = 0;      // 草稿

    public const STATUS_PUBLISHED = 1;  // 已发�?
    public const STATUS_ARCHIVED = 2;   // 已归�?
    /**
     * 可见性常�?
     */
    public const VISIBILITY_PRIVATE = 0;   // 私有

    public const VISIBILITY_PUBLIC = 1;    // 公开

    public const VISIBILITY_SHARED = 2;    // 共享

    /**
     * 时间戳字�?
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
     * 可填充字�?
     */
    protected array $fillable = [
        'title',
        'content',
        'excerpt',
        'creator_id',
        'status',
        'is_public',
        'user_id',
    ];

    /**
     * 隐藏字段.
     */
    protected array $hidden = [];

    /**
     * 获取笔记创建�?
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
        return $this->versions()->get();
    }

    /**
     * 笔记版本历史关联（一对多�?
     * @return HasMany
     */
    public function versions()
    {
        return $this->hasMany(NoteVersion::class, 'note_id', 'id')
            ->orderBy('version_number', 'desc');
    }
}

