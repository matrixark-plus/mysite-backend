<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Qbhy\HyperfAuth\AuthManager;

/**
 * 认证服务
 * 封装用户认证相关的业务逻辑
 */
class AuthService
{
    /**
     * @Inject
     * @var AuthManager
     */
    protected $auth;
    
    /**
     * @Inject
     * @var UserService
     */
    protected $userService;

    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * @Inject
     * @var LoggerFactory
     */
    protected $loggerFactory;
    
    public function __construct()
    {
        // 手动创建logger实例
        $this->logger = $this->loggerFactory->get('auth');
    }
    
    /**
     * 用户登录并生成令牌
     *
     * @param string $email 邮箱
     * @param string $password 密码
     * @return array [user, token]
     * @throws \InvalidArgumentException
     */
    public function login(string $email, string $password): array
    {
        try {
            // 记录auth_manager是否存在
            $this->logger->info('AuthManager status', [
                'auth_manager_exists' => $this->auth !== null,
                'auth_class' => get_class($this->auth ?? 'null')
            ]);

            // 验证用户凭据
            $credentials = ['email' => $email, 'password' => $password];
            
            // 使用默认guard登录
            $token = $this->auth->attempt($credentials);
            
            if (!$token) {
                throw new \InvalidArgumentException('邮箱或密码错误');
            }
            
            // 获取当前登录用户
            $user = $this->auth->user();
            
            return [
                'user' => $user,
                'token' => $token
            ];
        } catch (\Exception $e) {
            $this->logger->error('AuthService login failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * 用户注册并自动登录
     *
     * @param array $data 用户注册数据
     * @return array [user, token]
     * @throws \InvalidArgumentException
     */
    public function register(array $data): array
    {
        // 创建用户
        $user = $this->userService->createUser($data);
        
        // 自动登录并获取token
        $token = $this->auth->login($user);
        
        return [
            'user' => $user,
            'token' => $token
        ];
    }
    
    /**
     * 用户登出
     *
     * @return bool
     */
    public function logout(): bool
    {
        return $this->auth->logout();
    }
    
    /**
     * 获取格式化的用户信息
     *
     * @param User $user 用户模型
     * @param string $type 信息类型: 'basic', 'profile', 'full'
     * @return array
     */
    public function formatUserInfo(User $user, string $type = 'basic'): array
    {
        $basicInfo = [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->status
        ];
        
        switch ($type) {
            case 'profile':
                $basicInfo += [
                    'real_name' => $user->real_name,
                    'avatar' => $user->avatar,
                    'bio' => $user->bio,
                    'role' => $user->role
                ];
                break;
            case 'full':
                $basicInfo += [
                    'real_name' => $user->real_name,
                    'avatar' => $user->avatar,
                    'bio' => $user->bio,
                    'role' => $user->role,
                    'created_at' => $user->created_at
                ];
                break;
        }
        
        return $basicInfo;
    }
    
    /**
     * 修改密码并强制登出
     *
     * @param User $user 用户模型
     * @param string $currentPassword 当前密码
     * @param string $newPassword 新密码
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        // 修改密码
        $result = $this->userService->changePassword($user, $currentPassword, $newPassword);
        
        // 强制登出，需要重新登录
        $this->logout();
        
        return $result;
    }
    
    /**
     * 获取当前用户
     *
     * @return User|null
     */
    public function getCurrentUser(): ?User
    {
        try {
            return $this->auth->user();
        } catch (\Exception $e) {
            $this->logger->error('Failed to get current user', ['error' => $e->getMessage()]);
            return null;
        }
    }
}