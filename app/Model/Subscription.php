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
 * 订阅模型.
 */
class Subscription extends Model
{
    /**
     * 状态常量.
     */
    public const STATUS_UNVERIFIED = 0;
    public const STATUS_VERIFIED = 1;
    public const STATUS_CANCELLED = 2;

    /**
     * 表名.
     */
    protected ?string $table = 'subscriptions';

    /**
     * 主键.
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字段.
     */
    protected array $fillable = [
        'email',
        'token',
        'status',
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
        'status' => 'integer',
    ];
}