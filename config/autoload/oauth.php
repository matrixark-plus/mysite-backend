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
    // GitHub OAuth配置
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID', ''),
        'client_secret' => env('GITHUB_CLIENT_SECRET', ''),
        'redirect_uri' => env('APP_URL', '') . '/api/oauth/github/callback',
        'scopes' => ['user:email'],
    ],
    
    // Google OAuth配置
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
        'redirect_uri' => env('APP_URL', '') . '/api/oauth/google/callback',
        'scopes' => ['email', 'profile'],
    ],
    
    // 微信OAuth配置
    'wechat' => [
        'client_id' => env('WECHAT_CLIENT_ID', ''),
        'client_secret' => env('WECHAT_CLIENT_SECRET', ''),
        'redirect_uri' => env('APP_URL', '') . '/api/oauth/wechat/callback',
        'scopes' => ['snsapi_userinfo'],
    ],
    
    // 通用配置
    'common' => [
        // OAuth state存储配置
        'state' => [
            'driver' => 'redis', // session, redis
            'expire' => 300, // 5分钟过期
        ],
        
        // 用户绑定配置
        'user_binding' => [
            'enabled' => true,
            'auto_create' => true, // 自动创建用户
        ],
    ],
];