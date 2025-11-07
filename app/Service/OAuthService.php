<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Log\LoggerInterface;
use Yurun\OAuthLogin\GitHub\GitHubOAuth;
use Yurun\OAuthLogin\Google\GoogleOAuth;
use Yurun\OAuthLogin\Weixin\WeixinOAuth;

/**
 * OAuth服务
 * 使用yurunsoft/yurun-oauth-login组件处理第三方登录功能
 */
class OAuthService
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var JwtAuthService
     */
    protected $jwtAuthService;

    /**
     * 支持的第三方平台
     * @var array
     */
    protected $supportedPlatforms = ['github', 'wechat', 'google'];

    /**
     * OAuth实例缓存
     * @var array
     */
    protected $oauthInstances = [];

    public function __construct()
    {
        $container = ApplicationContext::getContainer();
        $this->config = $container->get(ConfigInterface::class);
        $this->logger = $container->get(LoggerInterface::class);
        $this->response = $container->get(ResponseInterface::class);
        $this->jwtAuthService = $container->get(JwtAuthService::class);
    }

    /**
     * 生成微信用户的用户名
     * 
     * @param mixed $openid 微信openid
     * @return string 生成的用户名
     */
    protected function generateWechatUsername($openid): string
    {
        // 使用前缀标识微信用户
        $prefix = 'wx_';
        
        // 检查openid是否为有效字符串
        if (is_string($openid) && !empty($openid)) {
            // 对有效openid进行哈希处理并取前12位，确保唯一性和安全性
            return $prefix . substr(md5($openid), 0, 12);
        }
        
        // 当openid无效时，生成随机哈希值
        return $prefix . substr(md5(uniqid('', true)), 0, 12);
    }
    

    /**
     * 获取平台的OAuth配置
     *
     * @param string $platform 平台名称
     * @return array 配置信息
     */
    public function getPlatformConfig(string $platform): array
    {
        $config = $this->config->get('oauth.' . $platform, []);
        
        // 如果配置不存在，返回默认配置
        if (empty($config)) {
            $config = [
                'client_id' => env(strtoupper($platform) . '_CLIENT_ID', ''),
                'client_secret' => env(strtoupper($platform) . '_CLIENT_SECRET', ''),
                'redirect_uri' => env('APP_URL', '') . '/api/oauth/' . $platform . '/callback',
                'scopes' => [],
            ];
        }

        return $config;
    }

    /**
     * 获取对应平台的OAuth实例
     *
     * @param string $platform 平台名称
     * @return mixed OAuth实例
     */
    protected function getOAuthInstance(string $platform)
    {
        if (!in_array($platform, $this->supportedPlatforms)) {
            throw new \InvalidArgumentException('不支持的平台: ' . $platform);
        }

        if (!isset($this->oauthInstances[$platform])) {
            $config = $this->getPlatformConfig($platform);
            
            switch ($platform) {
                case 'github':
                    $this->oauthInstances[$platform] = new GitHubOAuth($config['client_id'], $config['client_secret'], $config['redirect_uri']);
                    // 设置scopes
                    if (!empty($config['scopes'])) {
                        $this->oauthInstances[$platform]->setScopes($config['scopes']);
                    }
                    break;
                case 'google':
                    $this->oauthInstances[$platform] = new GoogleOAuth($config['client_id'], $config['client_secret'], $config['redirect_uri']);
                    // 设置scopes
                    if (!empty($config['scopes'])) {
                        $this->oauthInstances[$platform]->setScopes($config['scopes']);
                    }
                    break;
                case 'wechat':
                    $this->oauthInstances[$platform] = new WeixinOAuth($config['client_id'], $config['client_secret'], $config['redirect_uri']);
                    // 设置scope
                    if (!empty($config['scopes'][0])) {
                        $this->oauthInstances[$platform]->setScope($config['scopes'][0]);
                    }
                    break;
            }
        }

        return $this->oauthInstances[$platform];
    }

    /**
     * 生成授权URL
     *
     * @param string $platform 平台名称
     * @return string 授权URL
     */
    public function getAuthorizeUrl(string $platform): string
    {
        $oauth = $this->getOAuthInstance($platform);
        
        // 生成state并保存到session中
        $state = md5(uniqid('', true));
        // 这里应该使用Hyperf的Session或Redis组件来存储state
        // 为了简化，暂时只生成state
        
        // 设置state
        $oauth->setState($state);
        
        // 获取授权URL
        return $oauth->getAuthUrl();
    }

    /**
     * 处理回调并获取用户信息
     *
     * @param string $platform 平台名称
     * @param string $code 授权码
     * @param string $state state参数
     * @return array 用户信息
     */
    public function handleCallback(string $platform, string $code, string $state = ''): array
    {
        $oauth = $this->getOAuthInstance($platform);
        
        try {
            // 验证state
            if (!empty($state) && $state !== $oauth->getState()) {
                throw new \RuntimeException('State验证失败');
            }
            
            // 获取accessToken
            $accessToken = $oauth->getAccessToken($code);
            
            // 获取用户信息
            $userInfo = $oauth->getUserInfo();
            
            // 标准化用户信息
            $normalizedUserInfo = $this->normalizeUserInfo($platform, $userInfo);
            
            return [
                'platform' => $platform,
                'platform_user_id' => $normalizedUserInfo['id'],
                'user_info' => $normalizedUserInfo,
                'access_token' => $accessToken,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('OAuth回调处理失败: ' . $e->getMessage());
            throw new \RuntimeException('第三方登录失败: ' . $e->getMessage());
        }
    }

    /**
     * 标准化用户信息
     *
     * @param string $platform 平台名称
     * @param array $userInfo 原始用户信息
     * @return array 标准化后的用户信息
     */
    protected function normalizeUserInfo(string $platform, array $userInfo): array
    {
        switch ($platform) {
            case 'github':
                return [
                    'id' => (string)$userInfo['id'],
                    'name' => $userInfo['name'] ?? $userInfo['login'],
                    'username' => $userInfo['login'],
                    'email' => $userInfo['email'] ?? '',
                    'avatar' => $userInfo['avatar_url'] ?? '',
                    'bio' => $userInfo['bio'] ?? '',
                ];
                
            case 'google':
                return [
                    'id' => $userInfo['id'] ?? '',
                    'name' => $userInfo['name'] ?? '',
                    'username' => explode('@', $userInfo['email'])[0] ?? '',
                    'email' => $userInfo['email'] ?? '',
                    'avatar' => $userInfo['picture'] ?? '',
                    'bio' => '',
                ];
                
            case 'wechat':
                return [
                    'id' => $userInfo['openid'] ?? '',
                    'name' => $userInfo['nickname'] ?? '',
                    'username' => $this->generateWechatUsername($userInfo['openid'] ?? null),
                    'email' => '',
                    'avatar' => $userInfo['headimgurl'] ?? '',
                    'bio' => '',
                ];
                
            default:
                return $userInfo;
        }
    }

    /**
     * 生成JWT token
     *
     * @param array $userInfo 用户信息
     * @return array JWT信息
     */
    public function generateJwt(array $userInfo): array
    {
        // 这里应该查找或创建用户，然后生成JWT token
        // 简化处理，实际应该先在数据库中查找或创建用户
        $username = $userInfo['username'];
        $password = 'oauth_' . md5(uniqid('', true)); // 生成随机密码
        
        try {
            // 尝试使用用户名和密码登录（实际应该先创建用户）
            return $this->jwtAuthService->login($username, $password);
        } catch (\Throwable $e) {
            $this->logger->error('JWT生成失败: ' . $e->getMessage());
            throw new \RuntimeException('登录失败，请稍后重试');
        }
    }
}