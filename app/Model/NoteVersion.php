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

use Hyperf\Model\Relations\BelongsTo;

/**
 * 笔记版本历史模型.
 */
class NoteVersion extends Model
{
    /**
     * 时间戳字段.
     */
    public bool $timestamps = true;

    /**
     * 表名.
     */
    protected ?string $table = 'note_versions';

    /**
     * 主键.
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字段.
     */
    protected array $fillable = [
        'note_id',
        'content_snapshot',
        'title_snapshot',
        'version_number',
        'created_by',
    ];

    /**
     * 隐藏字段.
     */
    protected array $hidden = [];

    /**
     * 获取关联的笔记.
     * @return BelongsTo
     */
    public function note()
    {
        return $this->belongsTo(Note::class, 'note_id', 'id');
    }

    /**
     * 获取版本创建者.
     * @return BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
