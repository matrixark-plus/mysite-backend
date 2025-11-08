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
// 使用cURL测试登录API
function testLogin($email, $password)
{
    $url = 'http://localhost:9501/api/auth/login';
    $data = [
        'email' => $email,
        'password' => $password,
    ];

    // 创建cURL资源
    $ch = curl_init();

    // 设置cURL选项
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
    ]);

    // 执行请求并获取响应
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    // 检查是否有错误
    $error = curl_error($ch);

    // 关闭cURL资源
    curl_close($ch);

    // 输出结果
    echo "HTTP状态码: {$httpCode}\n";

    if ($error) {
        echo "❌ cURL错误: {$error}\n";
        return false;
    }

    // 解析JSON响应
    $result = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo '❌ 响应解析错误: ' . json_last_error_msg() . "\n";
        echo "原始响应: {$response}\n";
        return false;
    }

    echo '登录响应: ' . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    // 检查是否登录成功
    if (isset($result['code']) && $result['code'] === 200 && isset($result['data']['token'])) {
        echo "✅ 登录成功！获取到token\n";
        return true;
    }
    echo '❌ 登录失败: ' . ($result['message'] ?? '未知错误') . "\n";
    return false;
}

// 测试admin用户登录
echo "正在测试用户登录: admin@example.com\n";
echo "==========================================\n";

testLogin('admin@example.com', 'admin123');
