<?php
declare(strict_types=1);

namespace App\Service;


use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Qbhy\HyperfAuth\AuthManager;
use Qbhy\HyperfAuth\Exception\UnauthorizedException;

/**
 * 认证服务
 * 遵循hyperf-auth官方标准实现的认证服务类
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
     * @Inject
     * @var LoggerFactory
     */
    protected $loggerFactory;

    /**
     * 默认认证守卫
     */
    protected string $guard = 'jwt';

    /**
     * 用户登录并生成令牌
     * @param string $email 邮箱
     * @param string $password 密码
     * @param bool $remember 是否记住
     * @return array 包含token和用户信息
     * @throws \InvalidArgumentException 凭证无效时抛出
     * @throws \Exception 其他错误时抛出
     */
    public function login(string $email, string $password, bool $remember = false): array
    {
        try {
            // 验证参数并获取用户信息
            $user = $this->userService->login($email, $password);
            
            // 创建一个简单的对象来包装用户数组，使其兼容JWT守卫
            $userObj = (object)$user;
            
            // 使用hyperf-auth标准的login方法进行登录
            $this->auth->guard($this->guard)->login($userObj, $remember);
            
            // 获取token
            $token = $this->auth->guard($this->guard)->getToken()->__toString();
            
            return [
                'user' => $this->formatUserInfo($user, 'basic'),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => (int)config('auth.guards.jwt.ttl', 3600)
            ];
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            // 记录错误日志
            $this->loggerFactory->get('auth')->error('登录失败', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('登录失败，请稍后重试');
        }
    }

    /**
     * 用户注册并自动登录
     * @param array $data 用户注册数据
     * @return array 包含token和用户信息
     * @throws \InvalidArgumentException 参数无效时抛出
     * @throws \Exception 其他错误时抛出
     */
    public function register(array $data): array
    {
        try {
            // 利用UserService的createUser方法创建用户（包含完整验证）
            $user = $this->userService->createUser($data);
            
            // 创建一个简单的对象来包装用户数组，使其兼容JWT守卫
            $userObj = (object)$user;
            
            // 自动登录
            $this->auth->guard($this->guard)->login($userObj);
            
            // 获取token
            $token = $this->auth->guard($this->guard)->getToken()->__toString();
            
            return [
                'user' => $this->formatUserInfo($user, 'basic'),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => (int)config('auth.guards.jwt.ttl', 3600)
            ];
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            // 记录错误日志
            $this->loggerFactory->get('auth')->error('注册失败', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('注册失败，请稍后重试');
        }
    }

    /**
     * 用户登出
     * @return bool 登出结果
     * @throws \Exception 登出失败时抛出
     */
    public function logout(): bool
    {
        try {
            // 使用hyperf-auth标准的logout方法清除认证状态
            return $this->auth->guard($this->guard)->logout();
        } catch (\Exception $e) {
            // 记录错误日志
            $this->loggerFactory->get('auth')->error('登出失败', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception('登出失败');
        }
    }

    /**
     * 刷新令牌
     * @return array 新的token信息
     * @throws \Exception 刷新失败时抛出
     */
    public function refreshToken(): array
    {
        try {
            // 使用hyperf-auth标准的refresh方法刷新token
            $token = $this->auth->guard($this->guard)->refresh();
            
            return [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => (int)config('auth.guards.jwt.ttl', 3600)
            ];
        } catch (\Exception $e) {
            // 记录错误日志
            $this->loggerFactory->get('auth')->error('刷新token失败', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception('token已过期，请重新登录');
        }
    }

    /**
     * 获取格式化的用户信息
     * @param array $user 用户信息数组
     * @param string $type 信息类型: 'basic', 'profile', 'full'
     * @return array 格式化后的用户信息
     */
    public function formatUserInfo(array $user, string $type = 'basic'): array
    {
        $basicInfo = [
            'id' => $user['id'] ?? null,
            'username' => $user['username'] ?? '',
            'email' => $user['email'] ?? '',
            'status' => $user['status'] ?? 1
        ];
        
        switch ($type) {
            case 'profile':
                $basicInfo += [
                    'real_name' => $user['real_name'] ?? '',
                    'avatar' => $user['avatar'] ?? '',
                    'bio' => $user['bio'] ?? '',
                    'role' => $user['role'] ?? 'user'
                ];
                break;
            case 'full':
                $basicInfo += [
                    'real_name' => $user['real_name'] ?? '',
                    'avatar' => $user['avatar'] ?? '',
                    'bio' => $user['bio'] ?? '',
                    'role' => $user['role'] ?? 'user',
                    'created_at' => $user['created_at'] ?? null
                ];
                break;
        }
        
        return $basicInfo;
    }

    /**
     * 修改密码并强制登出
     * @param array $user 用户信息数组
     * @param string $currentPassword 当前密码
     * @param string $newPassword 新密码
     * @return bool 修改结果
     * @throws \InvalidArgumentException 参数无效时抛出
     * @throws \Exception 其他错误时抛出
     */
    public function changePassword(array $user, string $currentPassword, string $newPassword): bool
    {
        // 参数验证
        if (empty($currentPassword) || empty($newPassword)) {
            throw new \InvalidArgumentException('旧密码和新密码不能为空');
        }
        
        if (strlen($newPassword) < 6) {
            throw new \InvalidArgumentException('新密码长度不能少于6位');
        }

        try {
            // 修改密码
            $result = $this->userService->changePassword($user, $currentPassword, $newPassword);
            
            // 强制登出，需要重新登录
            $this->auth->guard($this->guard)->logout();
            
            return $result;
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            // 记录错误日志
            $this->loggerFactory->get('auth')->error('修改密码失败', [
                'user_id' => $user['id'] ?? null,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('修改密码失败，请稍后重试');
        }
    }

    /**
     * 获取当前用户
     * @return array 当前登录用户信息数组
     * @throws UnauthorizedException 未登录时抛出
     */
    public function getCurrentUser(): array
    {
        try {
            // 使用hyperf-auth标准的user方法获取当前用户
            $user = $this->auth->guard($this->guard)->user();
            if (!$user) {
                throw new UnauthorizedException('未授权');
            }
            // 确保返回的是数组类型
            return is_object($user) ? (array)$user : $user;
        } catch (UnauthorizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new UnauthorizedException('获取当前用户失败');
        }
    }
    
    /**
     * 获取当前用户信息
     * @param string $type 信息类型: 'basic', 'profile', 'full'
     * @return array 格式化后的用户信息
     * @throws UnauthorizedException 未登录时抛出
     */
    public function getCurrentUserInfo(string $type = 'profile'): array
    {
        $user = $this->getCurrentUser();
        return $this->formatUserInfo($user, $type);
    }
    
    /**
     * 更新用户资料
     * @param array $data 用户资料数据
     * @return array 更新后的用户信息
     * @throws \InvalidArgumentException 参数无效时抛出
     * @throws UnauthorizedException 未登录时抛出
     * @throws \Exception 更新失败时抛出
     */
    public function updateProfile(array $data): array
    {
        try {
            // 获取当前用户
            $user = $this->getCurrentUser();
            
            // 更新用户信息
            $updatedUser = $this->userService->updateUser($user['id'], $data);
            
            return $this->formatUserInfo($updatedUser, 'profile');
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (UnauthorizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            // 记录错误日志
            $this->loggerFactory->get('auth')->error('更新用户资料失败', [
                'user_id' => $user['id'] ?? null,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('更新资料失败，请稍后重试');
        }
    }
}