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
    // 默认邮件驱动
    'default' => env('MAIL_DRIVER', 'smtp'),
    
    // SMTP配置
    'smtp' => [
        // 默认配置
        'default' => [
            'host' => env('MAIL_HOST', 'smtp.example.com'),
            'port' => (int)env('MAIL_PORT', 465),
            'username' => env('MAIL_USERNAME', ''),
            'password' => env('MAIL_PASSWORD', ''),
            'encryption' => env('MAIL_ENCRYPTION', 'ssl'),
            'timeout' => 30,
        ],
        
        // 163邮箱配置
        '163' => [
            'host' => 'smtp.163.com',
            'port' => 465,
            'username' => env('MAIL_163_USERNAME', ''),
            'password' => env('MAIL_163_PASSWORD', ''),
            'encryption' => 'ssl',
            'timeout' => 30,
        ],
        
        // 阿里云企业邮箱配置
        'aliyun' => [
            'host' => 'smtp.mxhichina.com',
            'port' => 465,
            'username' => env('MAIL_ALIYUN_USERNAME', ''),
            'password' => env('MAIL_ALIYUN_PASSWORD', ''),
            'encryption' => 'ssl',
            'timeout' => 30,
        ],
        
        // Gmail配置
        'gmail' => [
            'host' => 'smtp.gmail.com',
            'port' => 465,
            'username' => env('MAIL_GMAIL_USERNAME', ''),
            'password' => env('MAIL_GMAIL_PASSWORD', ''),
            'encryption' => 'ssl',
            'timeout' => 30,
        ],
    ],
    
    // 发件人配置
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example App'),
    ],
    
    // 联系邮箱
    'contact_email' => env('CONTACT_EMAIL', 'admin@example.com'),
    
    // 异步发送配置
    'async' => [
        'enabled' => true,
        'pool_size' => 10,
    ],
    
    // 邮件模板配置
    'templates' => [
        'register_confirmation' => [
            'subject' => '注册确认',
            'view' => null, // 可以配置视图文件，当前使用硬编码的HTML
        ],
        'password_reset' => [
            'subject' => '密码重置请求',
            'view' => null,
        ],
        'contact_form' => [
            'subject' => '新的联系表单提交',
            'view' => null,
        ],
    ],
];