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
 * 订阅模型
 * 对应SubscribeService中使用的subscribes表
 */
class Subscribe extends Model
{
    /**
     * 状态常量.
     */
    public const STATUS_PENDING = 0;  // 待确认
    public const STATUS_CONFIRMED = 1; // 已确认

    /**
     * 订阅类型常量.
     */
    public const TYPE_BLOG = 'blog';

    /**
     * 表名.
     */
    protected ?string $table = 'subscribes';

    /**
     * 主键.
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字段.
     */
    protected array $fillable = [
        'email',
        'type',
        'token',
        'status',
        'confirmed_at',
        'created_at',
        'updated_at',
    ];

    /**
     * 隐藏字段.
     */
    protected array $hidden = [];

    /**
     * 时间戳字段.
     */
    protected array $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'confirmed_at' => 'timestamp',
        'status' => 'integer',
    ];
}