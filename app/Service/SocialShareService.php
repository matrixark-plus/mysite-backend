<?php

namespace App\Service;

use Hyperf\Utils\ApplicationContext;
use Hyperf\Config\ConfigFactory;

class SocialShareService
{
    /**
     * @var \Hyperf\Contract\ConfigInterface
     */
    protected $config;
    
    /**
     * 支持的社交媒体平台
     */
    const SUPPORTED_PLATFORMS = [
        'weibo',
        'wechat',
        'qq',
        'twitter',
        'facebook',
        'linkedin',
        'reddit',
        'telegram'
    ];
    
    public function __construct()
    {
        $container = ApplicationContext::getContainer();
        $this->config = $container->get(ConfigFactory::class)->get('social_share', []);
    }
    
    /**
     * 获取分享配置
     * 
     * @return array 分享配置信息
     */
    public function getShareConfig()
    {
        try {
            // 获取启用的平台配置
            $enabledPlatforms = $this->getEnabledPlatforms();
            
            // 构建分享配置
            $config = [
                'enabled_platforms' => $enabledPlatforms,
                'share_urls' => $this->generateShareUrls(),
                'default_description' => $this->config['default_description'] ?? '快来看看这个精彩内容！',
                'site_name' => $this->config['site_name'] ?? '我的网站'
            ];
            
            return $config;
        } catch (\Exception $e) {
            logger()->error('获取分享配置异常: ' . $e->getMessage());
            return [
                'enabled_platforms' => [],
                'share_urls' => [],
                'default_description' => '快来看看这个精彩内容！',
                'site_name' => '我的网站'
            ];
        }
    }
    
    /**
     * 获取启用的平台列表
     * 
     * @return array
     */
    protected function getEnabledPlatforms()
    {
        $enabledPlatforms = [];
        $platforms = $this->config['platforms'] ?? [];
        
        foreach (self::SUPPORTED_PLATFORMS as $platform) {
            if (isset($platforms[$platform]) && $platforms[$platform]['enabled']) {
                $enabledPlatforms[] = $platform;
            }
        }
        
        return $enabledPlatforms;
    }
    
    /**
     * 生成分享URL模板
     * 
     * @return array
     */
    protected function generateShareUrls()
    {
        return [
            'weibo' => 'https://service.weibo.com/share/share.php?url={url}&title={title}&pic={image}&appkey={appkey}',
            'twitter' => 'https://twitter.com/intent/tweet?url={url}&text={title}&via={via}',
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u={url}',
            'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}',
            'reddit' => 'https://www.reddit.com/submit?url={url}&title={title}',
            'telegram' => 'https://t.me/share/url?url={url}&text={title}'
        ];
    }
    
    /**
     * 生成指定平台的分享链接
     * 
     * @param string $platform 平台名称
     * @param string $url 分享的URL
     * @param string $title 分享标题
     * @param array $options 可选参数
     * @return string 分享链接
     */
    public function generateShareLink($platform, $url, $title, $options = [])
    {
        if (!in_array($platform, self::SUPPORTED_PLATFORMS)) {
            return '';
        }
        
        // 获取URL模板
        $shareUrls = $this->generateShareUrls();
        if (!isset($shareUrls[$platform])) {
            return '';
        }
        
        $template = $shareUrls[$platform];
        
        // 准备替换参数
        $params = [
            '{url}' => urlencode($url),
            '{title}' => urlencode($title),
            '{image}' => urlencode($options['image'] ?? ''),
            '{appkey}' => urlencode($options['appkey'] ?? ($this->config['platforms'][$platform]['appkey'] ?? '')),
            '{via}' => urlencode($options['via'] ?? ($this->config['platforms'][$platform]['via'] ?? ''))
        ];
        
        // 替换模板参数
        $shareLink = str_replace(array_keys($params), array_values($params), $template);
        
        return $shareLink;
    }
}