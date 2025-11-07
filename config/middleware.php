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
return [
    'http' => [
        // 必须的请求体解析中间件，用于处理JSON和表单请求
        Hyperf\HttpServer\Middleware\BodyParserMiddleware::class,
        App\Middleware\RequestLogMiddleware::class,
        // 跨域中间件
        App\Middleware\CorsMiddleware::class,
    ],
    'group' => [
        // 中间件组配置
        'auth' => [
            App\Middleware\JwtAuthMiddleware::class,
        ],
        'admin' => [
            App\Middleware\JwtAuthMiddleware::class,
        ],
    ],
    'alias' => [
        // 中间件别名映射
        'cors' => App\Middleware\CorsMiddleware::class,
        'jwt' => App\Middleware\JwtAuthMiddleware::class,
    ],
];