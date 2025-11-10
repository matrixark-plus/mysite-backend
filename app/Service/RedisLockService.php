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

namespace App\Service;

use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\RedisFactory;
use Psr\Log\LoggerInterface;
use Redis;
use RedisException;

/**
 * Redis分布式锁服务
 * 基于Redis实现的分布式锁，支持自动过期、避免死锁和锁重入.
 */
class RedisLockService
{
    /**
     * 锁前缀
     */
    public const LOCK_PREFIX = 'lock:';

    /**
     * @var Redis
     */
    protected $redis;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * RedisLockService constructor.
     */
    public function __construct(RedisFactory $redisFactory, LoggerFactory $loggerFactory)
    {
        $this->redis = $redisFactory->get('default');
        $this->logger = $loggerFactory->get('redis-lock');
    }

    /**
     * 获取锁
     *
     * @param string $key 锁键名
     * @param int $ttl 过期时间（秒）
     * @param int $waitTime 等待时间（毫秒）
     * @param int $retryDelay 重试间隔（毫秒）
     * @return false|string 锁标识字符串或失败返回false
     */
    public function lock(string $key, int $ttl = 10, int $waitTime = 0, int $retryDelay = 100): false|string
    {
        $lockKey = self::LOCK_PREFIX . $key;
        $lockValue = $this->generateLockValue();
        $startTime = microtime(true) * 1000;

        while (true) {
            try {
                // 使用SET命令的NX和EX选项实现原子性锁操作
                $result = $this->redis->set($lockKey, $lockValue, ['NX', 'EX' => $ttl]);

                if ($result) {
                    $this->logger->debug('获取锁成功', ['key' => $lockKey, 'value' => $lockValue]);
                    return $lockValue;
                }

                // 如果设置了等待时间，则等待并重试
                if ($waitTime > 0) {
                    $elapsed = microtime(true) * 1000 - $startTime;
                    if ($elapsed >= $waitTime) {
                        $this->logger->debug('获取锁超时', ['key' => $lockKey]);
                        return false;
                    }

                    // 短暂休眠后重试
                    usleep($retryDelay * 1000);
                } else {
                    // 非阻塞模式，直接返回失败
                    $this->logger->debug('获取锁失败（非阻塞）', ['key' => $lockKey]);
                    return false;
                }
            } catch (RedisException $e) {
                $this->logger->error('Redis锁操作异常', ['key' => $lockKey, 'error' => $e->getMessage()]);
                return false;
            }
        }
    }

    /**
     * 释放锁（安全释放，使用Lua脚本确保原子性）.
     *
     * @param string $key 锁键名
     * @param string $value 锁标识值
     * @return bool 是否成功释放
     */
    public function unlock(string $key, string $value): bool
    {
        $lockKey = self::LOCK_PREFIX . $key;

        try {
            // 使用Lua脚本确保释放锁的原子性，避免释放其他客户端的锁
            $script = <<<'LUA'
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('del', KEYS[1])
            else
                return 0
            end
            LUA;

            $result = $this->redis->eval($script, [$lockKey, $value], 1);

            if ($result) {
                $this->logger->debug('释放锁成功', ['key' => $lockKey]);
                return true;
            }
            $this->logger->debug('释放锁失败，锁不存在或不属于当前客户端', ['key' => $lockKey]);
            return false;
        } catch (RedisException $e) {
            $this->logger->error('Redis解锁操作异常', ['key' => $lockKey, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 延长锁的过期时间.
     *
     * @param string $key 锁键名
     * @param string $value 锁标识值
     * @param int $ttl 新的过期时间（秒）
     * @return bool 是否成功延长
     */
    public function extend(string $key, string $value, int $ttl): bool
    {
        $lockKey = self::LOCK_PREFIX . $key;

        try {
            // 使用Lua脚本确保延长锁的原子性
            $script = <<<'LUA'
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('pexpire', KEYS[1], ARGV[2])
            else
                return 0
            end
            LUA;

            $result = $this->redis->eval($script, [$lockKey, $value, $ttl * 1000], 1);

            if ($result) {
                $this->logger->debug('延长锁时间成功', ['key' => $lockKey, 'ttl' => $ttl]);
                return true;
            }
            $this->logger->debug('延长锁时间失败', ['key' => $lockKey]);
            return false;
        } catch (RedisException $e) {
            $this->logger->error('Redis延长锁操作异常', ['key' => $lockKey, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 检查锁是否存在且属于当前客户端.
     *
     * @param string $key 锁键名
     * @param string $value 锁标识值
     */
    public function isLockedByCurrentClient(string $key, string $value): bool
    {
        $lockKey = self::LOCK_PREFIX . $key;

        try {
            $storedValue = $this->redis->get($lockKey);
            return $storedValue === $value;
        } catch (RedisException $e) {
            $this->logger->error('检查锁状态异常', ['key' => $lockKey, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 使用锁执行回调函数（自动处理锁的获取和释放）.
     *
     * @param string $key 锁键名
     * @param callable $callback 回调函数
     * @param int $ttl 锁过期时间（秒）
     * @param mixed $default 默认返回值
     * @return mixed 回调函数的返回值或默认值
     */
    public function withLock(string $key, callable $callback, int $ttl = 10, mixed $default = false): mixed
    {
        $lockValue = $this->lock($key, $ttl);

        if (! $lockValue) {
            return $default;
        }

        try {
            return $callback();
        } finally {
            // 确保无论如何都释放锁
            $this->unlock($key, $lockValue);
        }
    }

    /**
     * 生成锁标识值
     *
     * @return string 唯一的锁标识
     */
    protected function generateLockValue(): string
    {
        // 使用进程ID、协程ID和随机字符串生成唯一标识
        $pid = posix_getpid() ?? mt_rand();
        $cid = co($tid = null)['id'] ?? 0;
        $random = bin2hex(random_bytes(8));
        return "{$pid}:{$cid}:{$random}";
    }
}
