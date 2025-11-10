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

/**
 * 联系表单模型.
 */
class Contact extends Model
{
    /**
     * 状态常量
     */
    public const STATUS_UNPROCESSED = 0;

    public const STATUS_PROCESSED = 1;

    /**
     * 表名.
     */
    protected ?string $table = 'contact_forms';

    /**
     * 主键.
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字段
     */
    protected array $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'phone',
        'ip',
        'status',
        'processed_at',
        'created_at',
        'updated_at',
    ];

    /**
     * 隐藏字段.
     */
    protected array $hidden = [];

    /**
     * 时间戳字段
     */
    protected array $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'processed_at' => 'timestamp',
        'status' => 'integer',
    ];
}

