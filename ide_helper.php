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

use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Hyperf\Context\ApplicationContext;
use Psr\Container\ContainerInterface;
use App\Model\User;

/**
 * Get logger instance.
 *
 * @param string $name The name of the logger
 * @return LoggerInterface
 */
function logger(string $name = 'app'): LoggerInterface
{
    return container()->get(LoggerFactory::class)->get($name);
}

/**
 * Get container instance.
 *
 * @return ContainerInterface
 */
function container(): ContainerInterface
{
    return ApplicationContext::getContainer();
}

/**
 * Get config instance.
 *
 * @return mixed
 */
function config()
{
    return container()->get('Hyperf\Config\ConfigInterface');
}

/**
 * Get event dispatcher instance.
 */

/**
 * @mixin \App\Model\User
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $password_hash
 * @property string $real_name
 * @property string $avatar
 * @property string $bio
 * @property string $role
 * @property int $status
 * @property string $created_at
 * @property string $updated_at
 */
class UserModel {}

/**
 * Get event dispatcher instance.
 *
 * @return mixed
 */
function dispatcher()
{
    return container()->get('Psr\EventDispatcher\EventDispatcherInterface');
}

/**
 * Get stdout logger instance.
 *
 * @return LoggerInterface
 */
function stdout_logger(): LoggerInterface
{
    return container()->get(LoggerInterface::class);
}

/**
 * Get database connection resolver instance.
 *
 * @return mixed
 */
function db()
{
    return container()->get('Hyperf\DbConnection\ConnectionResolverInterface');
}

/**
 * Get redis client instance.
 *
 * @return mixed
 */
function redis()
{
    return container()->get('Hyperf\Redis\RedisInterface');
}

/**
 * Get value from config.
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function config_get(string $key, $default = null)
{
    return config()->get($key, $default);
}

/**
 * Set value to config.
 *
 * @param string $key
 * @param mixed $value
 */
function config_set(string $key, $value): void
{
    config()->set($key, $value);
}

/**
 * Determine if the config exists.
 *
 * @param string $key
 * @return bool
 */
function config_has(string $key): bool
{
    return config()->has($key);
}

/**
 * Load the ide helper files from vendor directory
 */
$ideHelperPath = __DIR__ . '/vendor/hyperf/ide-helper/output';
if (is_dir($ideHelperPath)) {
    // 自动加载所有IDE助手文件
    $files = glob($ideHelperPath . '/**/*.php');
    if (is_array($files)) {
        foreach ($files as $file) {
            require_once $file;
        }
    }
}

// 引入Swoole IDE助手
swoole_ide_helper();

/**
 * 加载Swoole IDE助手
 *
 * @return void
 */
function swoole_ide_helper(): void
{
    $swooleIdeHelper = __DIR__ . '/vendor/swoole/ide-helper/src/swoole-ide-helper.php';
    if (file_exists($swooleIdeHelper)) {
        require_once $swooleIdeHelper;
    }
}
