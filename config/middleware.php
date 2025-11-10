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
use App\Middleware\CorsMiddleware;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\RateLimiterMiddleware;
use App\Middleware\RequestLogMiddleware;

/*
 * This file is part of Hyperf.
 *
 * @see     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    'http' => [
        // 必须的请求体解析中间件，用于处理JSON和表单请求
        // 使用Hyperf框架的内置中间件
        RequestLogMiddleware::class,
        // API访问频率限制中间件
        RateLimiterMiddleware::class,
        // 跨域中间件
        CorsMiddleware::class,
    ],

    // 路由中间件
    'route' => [
        // JWT认证中间件
        'auth' => JwtAuthMiddleware::class,
        // 管理员权限中间件
        'admin' => function () {
            return new JwtAuthMiddleware('admin');
        },
    ],

    'alias' => [
        // 中间件别名映射
        'cors' => CorsMiddleware::class,
        'jwt' => JwtAuthMiddleware::class,
        'rate_limiter' => RateLimiterMiddleware::class,
    ],
];
