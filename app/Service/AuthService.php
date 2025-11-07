<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\User;
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
     * @return array 包含token和用户信息
     * @throws \InvalidArgumentException 凭证无效时抛出
     * @throws \Exception 其他错误时抛出
     */
    public function login(string $email, string $password): array
    {
        // 参数验证
        if (empty($email) || empty($password)) {
            throw new \InvalidArgumentException('邮箱和密码不能为空');
        }

        try {
            $credentials = ['email' => $email, 'password' => $password];
            
            // 使用hyperf-auth标准的attempt方法进行认证
            $token = $this->auth->guard($this->guard)->attempt($credentials);
            
            if (!$token) {
                throw new \InvalidArgumentException('邮箱或密码错误');
            }

            // 获取认证后的用户信息
            $user = $this->auth->guard($this->guard)->user();
            
            // 返回用户信息和token
            return [
                'user' => $user,
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
        // 参数验证
        if (empty($data['email']) || empty($data['password']) || empty($data['username'])) {
            throw new \InvalidArgumentException('用户名、邮箱和密码不能为空');
        }

        try {
            // 检查邮箱是否已存在
            if ($this->userService->getUserByEmail($data['email'])) {
                throw new \InvalidArgumentException('邮箱已被注册');
            }

            // 创建用户
            $user = $this->userService->createUser($data);

            // 使用hyperf-auth标准的login方法自动登录
            $token = $this->auth->guard($this->guard)->login($user);

            // 返回用户信息和token
            return [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => (int)config('auth.guards.jwt.ttl', 3600)
            ];
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            // 记录错误日志
            $this->loggerFactory->get('auth')->error('注册失败', [
                'email' => $data['email'] ?? '',
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
     * @param User $user 用户模型
     * @param string $type 信息类型: 'basic', 'profile', 'full'
     * @return array 格式化后的用户信息
     */
    public function formatUserInfo(User $user, string $type = 'basic'): array
    {
        $basicInfo = [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->status ?? 1
        ];
        
        switch ($type) {
            case 'profile':
                $basicInfo += [
                    'real_name' => $user->real_name ?? '',
                    'avatar' => $user->avatar ?? '',
                    'bio' => $user->bio ?? '',
                    'role' => $user->role ?? 'user'
                ];
                break;
            case 'full':
                $basicInfo += [
                    'real_name' => $user->real_name ?? '',
                    'avatar' => $user->avatar ?? '',
                    'bio' => $user->bio ?? '',
                    'role' => $user->role ?? 'user',
                    'created_at' => $user->created_at
                ];
                break;
        }
        
        return $basicInfo;
    }

    /**
     * 修改密码并强制登出
     * @param User $user 用户模型
     * @param string $currentPassword 当前密码
     * @param string $newPassword 新密码
     * @return bool 修改结果
     * @throws \InvalidArgumentException 参数无效时抛出
     * @throws \Exception 其他错误时抛出
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
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
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('修改密码失败，请稍后重试');
        }
    }

    /**
     * 获取当前用户
     * @return User 当前登录用户对象
     * @throws UnauthorizedException 未登录时抛出
     */
    public function getCurrentUser(): User
    {
        try {
            // 使用hyperf-auth标准的user方法获取当前用户
            $user = $this->auth->guard($this->guard)->user();
            if (!$user) {
                throw new UnauthorizedException('未授权');
            }
            return $user;
        } catch (UnauthorizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new UnauthorizedException('获取当前用户失败');
        }
    }
}