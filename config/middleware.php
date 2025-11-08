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
    
    // 路由中间件
    'route' => [
        // JWT认证中间件
        'auth' => App\Middleware\JwtAuthMiddleware::class,
        // 管理员权限中间件
        'admin' => function() {
            return new App\Middleware\JwtAuthMiddleware('admin');
        },
    ],
    'group' => [
        // 权限管理接口中间件分组
        'permission' => [
            'auth',
        ],
        // 管理员权限接口中间件分组
        'admin_permission' => [
            'admin',
        ],
    ],
    'alias' => [
        // 中间件别名映射
        'cors' => App\Middleware\CorsMiddleware::class,
        'jwt' => App\Middleware\JwtAuthMiddleware::class,
    ],
];