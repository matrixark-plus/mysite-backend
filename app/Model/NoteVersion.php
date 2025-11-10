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

use Hyperf\DbConnection\Model\Relations\BelongsTo;

/**
 * 笔记版本历史模型.
 */
class NoteVersion extends Model
{
    /**
     * 时间戳字�?
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
     * 可填充字�?
     */
    protected array $fillable = [
        'note_id',
        'title',
        'content',
        'content_snapshot',
        'version_number',
    ];

    /**
     * 隐藏字段.
     */
    protected array $hidden = [];

    /**
     * 获取关联的笔�?
     * @return BelongsTo
     */
    public function note()
    {
        return $this->belongsTo(Note::class, 'note_id', 'id');
    }
}

