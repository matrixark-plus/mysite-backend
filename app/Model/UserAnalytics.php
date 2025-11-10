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
 * 用户分析模型.
 */
class UserAnalytics extends Model
{
    /**
     * 表名.
     */
    protected ?string $table = 'user_analytics';

    /**
     * 主键.
     */
    protected string $primaryKey = 'id';

    /**
     * 可填充字�?
     */
    protected array $fillable = [
        'user_id',
        'session_id',
        'event_type',
        'event_data',
        'url',
        'referrer',
        'user_agent',
        'ip_address',
        'browser',
        'device',
        'os',
    ];

    /**
     * 隐藏字段.
     */
    protected array $hidden = [
        'ip_address',
    ];

    /**
     * 时间戳字�?
     */
    protected array $casts = [
        'created_at' => 'timestamp',
        'event_data' => 'array',
    ];

    /**
     * 获取关联用户.
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

