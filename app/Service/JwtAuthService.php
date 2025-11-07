<?php

declare(strict_types=1);

namespace App\Service;

use Qbhy\HyperfAuth\AuthManager;
use Qbhy\HyperfAuth\Exception\UnauthorizedException;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Inject;
use App\Model\User;

/**
 * JWT认证服务
 * 封装JWT相关的常用操作
 */
class JwtAuthService
{
    /**
     * @Inject
     */
    protected AuthManager $auth;

    /**
     * @Inject
     */
    protected ConfigInterface $config;
    
    /**
     * @Inject
     */
    protected \App\Service\UserService $userService;

    public function __construct()
    {
        // 所有依赖通过@Inject注解注入，不再需要手动获取容器
    }

    /**
     * 用户登录
     *
     * @param string $username 用户名或邮箱
     * @param string $password 密码
     * @return array 返回token和用户信息
     * @throws UnauthorizedException 认证失败异常
     */
    public function login(string $username, string $password): array
    {
        // 尝试通过邮箱查找用户
        $user = $this->userService->getUserByEmail($username);
        
        if (!$user) {
            // 如果邮箱查找失败，尝试通过用户名查找
            $user = $this->userService->getUserByUsername($username);
        }
        
        if (!$user || !password_verify($password, $user->password_hash)) {
            throw new UnauthorizedException('用户名或密码错误');
        }
        
        // 验证通过后生成token
        $guard = $this->auth->guard('jwt');
        $token = $guard->login($user);

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->config->get('auth.guards.jwt.ttl', 7200),
            'user' => $user,
        ];
    }

    /**
     * 刷新token
     *
     * @return array 返回新的token信息
     * @throws UnauthorizedException 认证失败异常
     */
    public function refresh(): array
    {
        $guard = $this->auth->guard('jwt');
        $token = $guard->refresh();

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->config->get('auth.guards.jwt.ttl', 7200),
        ];
    }

    /**
     * 注销登录
     *
     * @return bool 是否成功
     */
    public function logout(): bool
    {
        $guard = $this->auth->guard('jwt');
        return $guard->logout();
    }

    /**
     * 获取当前登录用户
     *
     * @return User|null 用户模型
     */
    public function user(): ?User
    {
        $guard = $this->auth->guard('jwt');
        return $guard->user();
    }

    /**
     * 生成token
     *
     * @param User $user 用户模型
     * @return string token
     */
    public function generateToken(User $user): string
    {
        $guard = $this->auth->guard('jwt');
        return $guard->login($user);
    }

    /**
     * 验证token
     *
     * @param string $token token字符串
     * @return User|null 验证成功返回用户，否则返回null
     */
    public function validateToken(string $token): ?User
    {
        try {
            $guard = $this->auth->guard('jwt');
            $guard->setToken($token);
            return $guard->user();
        } catch (\Throwable $e) {
            return null;
        }
    }
}