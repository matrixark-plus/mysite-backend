<?php

declare(strict_types=1);

namespace App\Model;

/**
 * 笔记版本历史模型
 */
class NoteVersion extends Model
{
    /**
     * 表名
     * @var ?string
     */
    protected ?string $table = 'note_versions';

    /**
     * 主键
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字段
     * @var array
     */
    protected array $fillable = [
        'note_id',
        'content_snapshot',
        'title_snapshot',
        'version_number',
        'created_by',
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
     * 获取关联的笔记
     * @return \Hyperf\Model\Relations\BelongsTo
     */
    public function note()
    {
        return $this->belongsTo(Note::class, 'note_id', 'id');
    }
    
    /**
     * 获取版本创建者
     * @return \Hyperf\Model\Relations\BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}