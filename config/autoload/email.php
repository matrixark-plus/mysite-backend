<?php

return [
    'email' => [
        // SMTP服务器配置
        'host' => env('EMAIL_HOST', 'smtp.example.com'),
        'port' => env('EMAIL_PORT', 587),
        'username' => env('EMAIL_USERNAME', ''),
        'password' => env('EMAIL_PASSWORD', ''),
        'secure' => env('EMAIL_SECURE', 'tls'), // tls or ssl
        
        // 发件人配置
        'from' => [
            'address' => env('EMAIL_FROM_ADDRESS', 'noreply@example.com'),
            'name' => env('EMAIL_FROM_NAME', '个人网站'),
        ],
        
        // 验证码配置
        'verify_code' => [
            'length' => 6,
            'expire_minutes' => 10,
        ],
    ],
];