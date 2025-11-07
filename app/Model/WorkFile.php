<?php

declare(strict_types=1);

namespace App\Model;

/**
 * 作品文件模型
 */
class WorkFile extends Model
{
    /**
     * 表名
     * @var ?string
     */
    protected ?string $table = 'work_files';

    /**
     * 主键
     * @var string
     */
    protected string $primaryKey = 'id';

    /**
     * 状态常量
     */
    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    /**
     * 可填充字段
     * @var array
     */
    protected array $fillable = [
        'work_id',
        'name',
        'path',
        'size',
        'mime_type',
        'extension',
        'download_count',
        'is_primary',
        'status',
        'created_at',
        'updated_at',
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
        'size' => 'integer',
        'download_count' => 'integer',
        'is_primary' => 'boolean',
        'status' => 'integer',
    ];

    /**
     * 获取所属作品
     * @return \Hyperf\Model\Relations\BelongsTo
     */
    public function work()
    {
        return $this->belongsTo(Work::class, 'work_id', 'id');
    }

    /**
     * 获取文件的完整URL
     * @return string
     */
    public function getFileUrlAttribute()
    {
        // 这里可以根据实际存储位置返回完整URL
        // 例如：return config('app.url') . '/storage/' . $this->path;
        return '/' . $this->path;
    }

    /**
     * 获取文件大小的可读格式
     * @return string
     */
    public function getReadableSizeAttribute()
    {
        $size = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($size >= 1024 && $i < 4) {
            $size /= 1024;
            $i++;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
}