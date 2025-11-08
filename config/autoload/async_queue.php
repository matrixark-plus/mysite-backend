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
use Hyperf\AsyncQueue\Driver\RedisDriver;

return [
    'default' => [
        'driver' => RedisDriver::class,
        'channel' => 'hyperf:async-queue:default',
        'timeout' => 2,
        'retry_seconds' => 5,
        'handle_timeout' => 10,
        'processes' => 1,
        'max_retries' => 3,
        'pool' => 'default',
    ],
];
