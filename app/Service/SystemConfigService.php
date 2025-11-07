<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\SystemConfig;
use Hyperf\Cache\CacheManager;
use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;

/**
 * 系统配置服务
 */
class SystemConfigService
{
    /**
     * 缓存键前缀
     */
    const CACHE_PREFIX = 'system_config:';
    
    /**
     * 配置缓存时间（秒）
     */
    const CACHE_TTL = 3600; // 1小时
    
    /**
     * 缓存管理器实例
     * @var CacheManager
     */
    protected $cache;
    
    /**
     * 构造函数
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
        $cacheKey = self::CACHE_PREFIX . $key;
        
        // 尝试从缓存获取
        $cachedValue = $this->cache->get($cacheKey);
        if ($cachedValue !== null) {
            return $cachedValue;
        }
        
        // 从数据库获取
        $config = SystemConfig::query()
            ->where('key', $key)
            ->where('status', SystemConfig::STATUS_ENABLED)
            ->first();
        
        if (!$config) {
            return $default;
        }
        
        $value = $config->getValue();
        
        // 缓存配置值
        $this->cache->set($cacheKey, $value, self::CACHE_TTL);
        
        return $value;
    }
    
    /**
     * 获取所有系统配置
     * @return array
     */
    public function getAllConfigs()
    {
        $cacheKey = self::CACHE_PREFIX . 'all';
        
        // 尝试从缓存获取
        $cachedConfigs = $this->cache->get($cacheKey);
        if ($cachedConfigs !== null) {
            return $cachedConfigs;
        }
        
        // 从数据库获取所有启用的配置
        $configs = SystemConfig::query()
            ->where('status', SystemConfig::STATUS_ENABLED)
            ->orderBy('sort', 'asc')
            ->get();
        
        // 转换为键值对数组
        $result = [];
        foreach ($configs as $config) {
            $result[$config->key] = $config->getValue();
        }
        
        // 缓存所有配置
        $this->cache->set($cacheKey, $result, self::CACHE_TTL);
        
        return $result;
    }
    
    /**
     * 设置配置值
     * @param string $key 配置键名
     * @param mixed $value 配置值
     * @return bool
     */
    public function setConfig(string $key, $value): bool
    {
        // 查找配置
        $config = SystemConfig::query()
            ->where('key', $key)
            ->first();
        
        if (!$config) {
            // 配置不存在，创建新配置
            $config = new SystemConfig();
            $config->key = $key;
            $config->type = is_array($value) ? SystemConfig::TYPE_JSON : 
                           (is_bool($value) ? SystemConfig::TYPE_BOOLEAN :
                           (is_numeric($value) ? SystemConfig::TYPE_NUMBER :
                           SystemConfig::TYPE_STRING));
        }
        
        // 设置配置值
        $config->setValue($value);
        
        // 保存配置
        $result = $config->save();
        
        if ($result) {
            // 清除缓存
            $this->clearConfigCache($key);
        }
        
        return $result;
    }
    
    /**
     * 批量设置配置
     * @param array $configs 配置数组 [key => value]
     * @return bool
     */
    public function setConfigs(array $configs): bool
    {
        Db::beginTransaction();
        
        try {
            foreach ($configs as $key => $value) {
                $this->setConfig($key, $value);
            }
            
            Db::commit();
            // 清除所有缓存
            $this->clearAllCache();
            return true;
        } catch (\Exception $e) {
            Db::rollBack();
            return false;
        }
    }
    
    /**
     * 清除指定配置的缓存
     * @param string $key 配置键名
     */
    public function clearConfigCache(string $key)
    {
        $this->cache->delete(self::CACHE_PREFIX . $key);
        $this->cache->delete(self::CACHE_PREFIX . 'all');
    }
    
    /**
     * 清除所有配置缓存
     */
    public function clearAllCache()
    {
        // 获取所有缓存键并删除
        // 注意：实际应用中可能需要使用特定的缓存删除策略
        // 这里简化处理，删除所有配置相关缓存
        $pattern = self::CACHE_PREFIX . '*';
        $keys = $this->cache->getMultipleKeys($pattern);
        
        if (!empty($keys)) {
            $this->cache->deleteMultiple($keys);
        }
    }
}