<?php

declare(strict_types=1);
/**
 * This file is part of qbhy/hyperf-auth.
 *
 * @link     https://github.com/qbhy/hyperf-auth
 * @document https://github.com/qbhy/hyperf-auth/blob/master/README.md
 * @contact  qbhy0715@qq.com
 * @license  https://github.com/qbhy/hyperf-auth/blob/master/LICENSE
 */
use Qbhy\SimpleJwt\Encoders;
use Qbhy\SimpleJwt\EncryptAdapters as Encrypter;

return [
    // 默认认证配置
    'default' => [
        'guard' => 'jwt',
        'provider' => 'users',
    ],
    
    // 认证守卫配置
    'guards' => [
        // JWT认证守卫
        'jwt' => [
            'driver' => Qbhy\HyperfAuth\Guard\JwtGuard::class,
            'provider' => 'users',

            // JWT配置
            'secret' => env('JWT_SECRET', 'your-jwt-secret-key'),
            'header_name' => env('JWT_HEADER_NAME', 'Authorization'),
            'ttl' => (int) env('JWT_TTL', 60 * 60 * 24), // 24小时
            'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 60 * 60 * 24 * 7), // 7天
            'default' => Encrypter\SHA1Encrypter::class,
            'drivers' => [
                Encrypter\SHA1Encrypter::alg() => Encrypter\SHA1Encrypter::class,
                Encrypter\Md5Encrypter::alg() => Encrypter\Md5Encrypter::class,
            ],
            'encoder' => new Encoders\Base64UrlSafeEncoder(),
            'prefix' => env('JWT_PREFIX', 'Bearer '),
        ],
        
        // 会话认证守卫（可选）
        'session' => [
            'driver' => Qbhy\HyperfAuth\Guard\SessionGuard::class,
            'provider' => 'users',
        ],
    ],
    
    // 用户提供者配置
    'providers' => [
        'users' => [
            'driver' => \App\Provider\ArrayProvider::class,
            'model' => App\Model\User::class, // 我们的实现将确保返回兼容的用户信息
            'rules' => [], // 这里可以添加额外的验证规则
        ],
    ],
];
