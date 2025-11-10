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

use Exception;
use Hyperf\Cache\CacheManager;
use Hyperf\Context\ApplicationContext;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 系统配置服务
 */
class SystemConfigService
{
    /**
     * 缓存键前缀
     */
    public const CACHE_PREFIX = 'system_config:';

    /**
     * 配置缓存时间（秒）.
     */
    public const CACHE_TTL = 3600; // 1小时

    /**
     * 缓存管理器实例.
     * @var CacheManager
     */
    protected $cache;

    /**
     * @Inject
     * @var EnvironmentFileService
     */
    protected $environmentFileService;

    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 构造函数.
     */
    public function __construct()
    {
        $this->cache = ApplicationContext::getContainer()->get(CacheManager::class);
    }

    /**
     * 根据键名获取配置值
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function getConfig(string $key, $default = null)
    {
        try {
            $cacheKey = self::CACHE_PREFIX . $key;

            // 尝试从缓存获取
            $cachedValue = $this->cache->get($cacheKey);
            if ($cachedValue !== null) {
                return $cachedValue;
            }

            // 从环境变量文件获取
            $value = $this->environmentFileService->getEnvVar($key);

            if ($value === null) {
                return $default;
            }

            // 缓存配置值
            $this->cache->set($cacheKey, $value, self::CACHE_TTL);

            return $value;
        } catch (Exception $e) {
            $this->logger->error('获取配置值失败: ' . $e->getMessage(), ['key' => $key]);
            return $default;
        }
    }

    /**
     * 获取多个配置项.
     * @param array $keys 配置项键名列表
     * @return array 配置项值列表，键名为配置项键名，值为配置项值
     */
    public function getMultipleConfigs(array $keys): array
    {
        $configs = [];
        foreach ($keys as $key) {
            $configs[$key] = $this->getConfig($key);
        }
        return $configs;
    }

    /**
     * 获取所有系统配置.
     * @return array
     */
    public function getAllConfigs()
    {
        try {
            $cacheKey = self::CACHE_PREFIX . 'all';

            // 尝试从缓存获取
            $cachedConfigs = $this->cache->get($cacheKey);
            if ($cachedConfigs !== null) {
                return $cachedConfigs;
            }

            // 从环境变量文件获取所有配置
            $result = $this->environmentFileService->readEnvFile();

            // 缓存所有配置
            $this->cache->set($cacheKey, $result, self::CACHE_TTL);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('获取所有系统配置失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 设置配置值
     * @param string $key 配置键名
     * @param mixed $value 配置值
     */
    public function setConfig(string $key, $value): bool
    {
        try {
            // 通过环境变量文件服务更新或创建配置
            $result = $this->environmentFileService->updateEnvVar($key, $value);

            if ($result) {
                // 清除缓存
                $this->clearConfigCache($key);
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('设置配置值失败: ' . $e->getMessage(), ['key' => $key]);
            return false;
        }
    }

    /**
     * 批量设置配置.
     * @param array $configs 配置数组 [key => value]
     */
    public function setConfigs(array $configs): bool
    {
        try {
            // 批量更新环境变量
            $result = $this->environmentFileService->batchUpdateEnvVars($configs);

            if ($result) {
                // 清除所有缓存
                $this->clearAllCache();
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('批量设置配置失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 清除指定配置的缓存.
     * @param string $key 配置键名
     */
    public function clearConfigCache(string $key)
    {
        $this->cache->delete(self::CACHE_PREFIX . $key);
        $this->cache->delete(self::CACHE_PREFIX . 'all');
    }

    /**
     * 清除所有配置缓存.
     */
    public function clearAllCache()
    {
        // 获取所有缓存键并删除
        // 注意：实际应用中可能需要使用特定的缓存删除策略
        // 这里简化处理，删除所有配置相关缓存
        $pattern = self::CACHE_PREFIX . '*';
        // 获取匹配模式的所有键
        $keys = $this->cache->keys($pattern);

        if (! empty($keys)) {
            $this->cache->deleteMultiple($keys);
        }
    }
}
