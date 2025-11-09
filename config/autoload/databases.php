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
use function Hyperf\Support\env;

return [
    'default' => [
        'driver' => env('DB_DRIVER', 'mysql'),
        'host' => env('DB_HOST', 'localhost'),
        'database' => env('DB_DATABASE', 'hyperf'),
        'port' => env('DB_PORT', 3306),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8'),
        'collation' => env('DB_COLLATION', 'utf8_unicode_ci'),
        'prefix' => env('DB_PREFIX', ''),
        'pool' => [
            // 最小连接数，根据服务器CPU核心数和预期并发量调整
            'min_connections' => (int) env('DB_MIN_CONNECTIONS', 2),
            // 最大连接数，根据服务器性能和数据库最大连接数限制调整
            'max_connections' => (int) env('DB_MAX_CONNECTIONS', 50),
            // 连接超时时间，适当缩短以快速失败并释放资源
            'connect_timeout' => (float) env('DB_CONNECT_TIMEOUT', 5.0),
            // 等待连接池连接的超时时间
            'wait_timeout' => (float) env('DB_WAIT_TIMEOUT', 3.0),
            // 心跳检测，定期检查连接是否有效，设置为60秒
            'heartbeat' => (int) env('DB_HEARTBEAT', 60),
            // 最大空闲时间，超过此时间的空闲连接将被回收
            'max_idle_time' => (float) env('DB_MAX_IDLE_TIME', 60),
        ],
        'commands' => [
            'gen:model' => [
                'path' => 'app/Model',
                'force_casts' => true,
                'inheritance' => 'Model',
            ],
        ],
    ],
];
