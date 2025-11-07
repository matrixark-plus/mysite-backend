<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerInterface;

/**
 * 系统配置服务
 * 用于管理网站全局配置，支持配置的获取和更新
 */
class ConfigService
{
    /**
     * @var RedisFactory
     */
    protected $redis;
    
    /**
     * @var ConfigInterface
     */
    protected $config;
    
    /**
     * 配置缓存键前缀
     */
    protected $cachePrefix = 'sys_config:';
    
    /**
     * 缓存过期时间（秒）
     */
    protected $cacheExpire = 3600;
    
    public function __construct(ContainerInterface $container)
    {
        $this->redis = $container->get(RedisFactory::class);
        $this->config = $container->get(ConfigInterface::class);
    }
    
    /**
     * 获取配置
     * 
     * @param string|null $key 配置键名，不提供则获取所有配置
     * @return array|mixed 配置值
     */
    public function getConfig(?string $key = null)
    {
        // 从缓存获取所有配置
        $config = $this->getConfigFromCache();
        
        // 如果缓存不存在，从数据库或默认配置获取
        if (empty($config)) {
            $config = $this->getDefaultConfig();
            $this->setConfigToCache($config);
        }
        
        // 如果指定了键名，返回指定配置
        if ($key !== null) {
            return $config[$key] ?? null;
        }
        
        return $config;
    }
    
    /**
     * 更新配置
     * 
     * @param array $data 配置数据
     * @return bool 是否成功
     */
    public function updateConfig(array $data): bool
    {
        // 获取现有配置
        $currentConfig = $this->getConfig();
        
        // 合并新配置
        $updatedConfig = array_merge($currentConfig, $data);
        
        // 更新缓存
        return $this->setConfigToCache($updatedConfig);
    }
    
    /**
     * 从缓存获取配置
     * 
     * @return array 配置数组
     */
    protected function getConfigFromCache(): array
    {
        try {
            $redis = $this->redis->get();
            $key = $this->cachePrefix . 'all';
            $config = $redis->get($key);
            
            if ($config) {
                return json_decode($config, true) ?: [];
            }
        } catch (\Throwable $e) {
            // 缓存获取失败，记录错误但不影响系统运行
            $this->logError('获取配置缓存失败', ['error' => $e->getMessage()]);
        }
        
        return [];
    }
    
    /**
     * 设置配置到缓存
     * 
     * @param array $config 配置数组
     * @return bool 是否成功
     */
    protected function setConfigToCache(array $config): bool
    {
        try {
            $redis = $this->redis->get();
            $key = $this->cachePrefix . 'all';
            $redis->setex($key, $this->cacheExpire, json_encode($config, JSON_UNESCAPED_UNICODE));
            return true;
        } catch (\Throwable $e) {
            // 缓存设置失败，记录错误
            $this->logError('设置配置缓存失败', ['error' => $e->getMessage(), 'config' => $config]);
            return false;
        }
    }
    
    /**
     * 获取默认配置
     * 
     * @return array 默认配置数组
     */
    protected function getDefaultConfig(): array
    {
        return [
            'site_name' => '个人网站',
            'site_description' => '这是一个基于Hyperf框架的个人网站',
            'contact_email' => 'admin@example.com',
            'social_links' => [
                'github' => '',
                'twitter' => '',
                'weibo' => '',
            ],
            'comment_settings' => [
                'enabled' => true,
                'need_approval' => true,
                'max_length' => 1000,
            ],
            'pagination' => [
                'default_page_size' => 10,
                'max_page_size' => 100,
            ],
        ];
    }
    
    /**
     * 记录错误日志
     * 
     * @param string $message 错误消息
     * @param array $context 上下文信息
     */
    protected function logError(string $message, array $context = []): void
    {
        // 记录错误日志，这里简化处理
        error_log($message . ':' . json_encode($context));
    }
}