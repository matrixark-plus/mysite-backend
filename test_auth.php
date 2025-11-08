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
// 检查配置文件
$configPath = __DIR__ . '/config/autoload/auth.php';
if (! file_exists($configPath)) {
    echo "❌ 配置文件不存在: {$configPath}\n";
    exit(1);
}

echo "✅ 配置文件存在\n";

// 读取并解析配置文件
$config = include $configPath;
if (! is_array($config) || ! isset($config['guards']['jwt'])) {
    echo "❌ 配置文件格式不正确，缺少jwt guard配置\n";
    exit(1);
}

echo "✅ JWT Guard配置存在\n";

echo "JWT Guard配置详情:\n";
echo '- 驱动: ' . ($config['guards']['jwt']['driver'] ?? '未配置') . "\n";
echo '- 模型: ' . ($config['guards']['jwt']['provider']['model'] ?? '未配置') . "\n";
echo '- TTL: ' . ($config['guards']['jwt']['ttl'] ?? '未配置') . " 秒\n";

echo "\n✅ hyperf-auth配置验证完成\n";
