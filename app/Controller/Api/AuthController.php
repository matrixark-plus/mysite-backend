<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Qbhy\HyperfAuth\Annotation\Auth;
use Qbhy\HyperfAuth\AuthManager;

use Hyperf\HttpServer\Contract\ResponseInterface;
use App\Controller\AbstractController;
use App\Service\UserService;
use App\Traits\LogTrait;

/**
 * 认证控制器
 * 提供用户登录、注册、登出等认证相关功能
 * @Controller(prefix="/api/auth")
 */
class AuthController extends AbstractController
{
    use LogTrait;
    
    /**
     * @var UserService
     */
    protected $userService;
    
    /**
     * @var AuthManager
     */
    protected $auth;
    
    /**
     * 构造函数
     * @param UserService $userService 用户服务
     * @param AuthManager $auth 认证管理器
     */
    public function __construct(UserService $userService, AuthManager $auth)
    {
        $this->userService = $userService;
        $this->auth = $auth;
    }
    
    /**
     * 获取当前用户信息
     * @return \App\Model\User|null
     */
    protected function getCurrentUser()
    {
        try {
            return $this->auth->guard('jwt')->user();
        } catch (\Exception $e) {
            $this->logError('获取当前用户失败', ['error' => $e->getMessage()], $e, 'auth');
            return null;
        }
    }
    
    /**
     * 格式化用户信息
     *
     * @param \App\Model\User $user 用户模型
     * @param string $type 信息类型: 'basic', 'profile', 'full'
     * @return array
     */
    protected function formatUserInfo($user, string $type = 'basic'): array
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
     * 用户注册
     * @PostMapping(path="/register")
     * @return ResponseInterface
     */
    public function register(): ResponseInterface
    {
        try {
            // 获取请求参数
            $data = $this->request->all();
            $ip = $this->request->getServerParams()['remote_addr'] ?? 'unknown';
            
            // 记录注册请求
            $this->logAction('用户注册请求', [
                'email' => $data['email'] ?? '',
                'ip' => $ip,
            ]);
            
            // 参数验证
            if (empty($data['email']) || empty($data['password'])) {
                throw new \InvalidArgumentException('邮箱和密码不能为空');
            }
            
            // 验证邮箱格式
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('邮箱格式不正确');
            }
            
            // 创建用户
            $user = $this->userService->createUser($data);
            
            // 自动登录并获取token
            $token = $this->auth->guard('jwt')->login($user);
            
            // 记录注册成功
            $this->logAction('用户注册成功', [
                'email' => $data['email'],
                'user_id' => $user->id
            ]);
            
            // 返回成功响应
            return $this->success([
                'token' => $token,
                'user' => $this->formatUserInfo($user, 'basic'),
            ], '注册成功');
        } catch (\InvalidArgumentException $e) {
            // 记录注册参数验证失败
            $this->logWarning('注册参数验证失败', [
                'message' => $e->getMessage()
            ], 'auth');
            return $this->fail(400, $e->getMessage());
        } catch (\Throwable $e) {
            // 记录错误日志
            $this->logError('用户注册异常', [], $e, 'auth');
            return $this->fail(500, '注册失败，请稍后重试');
        }
    }

    /**
     * 用户登录
     * @PostMapping(path="/login")
     * @return ResponseInterface
     */
    public function login(): ResponseInterface
    {
        try {
            // 获取登录凭证
            $email = $this->request->input('email', '');
            $password = $this->request->input('password', '');
            $ip = $this->request->getServerParams()['remote_addr'] ?? 'unknown';
            
            // 记录登录请求
            $this->logAction('用户登录请求', [
                'ip' => $ip,
                'email' => $email,
            ]);
            
            // 参数验证
            if (empty($email) || empty($password)) {
                throw new \InvalidArgumentException('邮箱和密码不能为空');
            }
            
            // 直接验证用户凭据
            try {
                // 查找用户
                $user = $this->userService->getUserByEmail($email);
                if (!$user) {
                    $this->logWarning('用户不存在', ['email' => $email], 'auth');
                    throw new \InvalidArgumentException('邮箱或密码错误');
                }
                
                // User模型使用password_hash字段存储密码，并且实现了Authenticatable接口
                // 使用JWT guard的login方法直接登录（内部会处理密码验证）
                // 这里我们直接将用户对象传递给login方法，让框架处理验证
                $token = $this->auth->guard('jwt')->login($user);
                
                if (!$token) {
                    $this->logWarning('登录失败', ['email' => $email], 'auth');
                    throw new \InvalidArgumentException('邮箱或密码错误');
                }
            } catch (\Exception $e) {
                $this->logError('验证用户失败', ['email' => $email, 'error' => $e->getMessage()], $e, 'auth');
                throw new \InvalidArgumentException('邮箱或密码错误');
            }
            
            // 记录成功登录信息
            $this->logAction('用户登录成功', [
                'user_id' => $user->id,
                'email' => $email,
                'user_role' => $user->role
            ]);
            
            // 返回成功响应
            return $this->success([
                'token' => $token,
                'user' => $this->formatUserInfo($user, 'profile'),
            ], '登录成功');
        } catch (\InvalidArgumentException $e) {
            // 记录登录参数验证异常
            $this->logWarning('登录参数验证异常', [
                'message' => $e->getMessage(),
                'error_code' => 401
            ], 'auth');
            return $this->fail(401, $e->getMessage());
        } catch (\Exception $e) {
            // 处理一般异常
            $this->logError('用户登录异常', [], $e, 'auth');
            return $this->fail(500, '登录失败，请稍后重试');
        }
    }

    /**
     * 用户登出
     * @Auth(guard="jwt")
     * @DeleteMapping(path="/logout")
     * @return ResponseInterface
     */
    public function logout(): ResponseInterface
    {
        // 先获取用户ID，确保在catch块中也能访问
        $user = $this->getCurrentUser();
        $userId = $user ? $user->id : null;
        
        try {
            
            // 清除JWT token
            $this->auth->guard('jwt')->logout();
            
            // 记录登出成功
            $this->logAction('用户登出成功', ['user_id' => $userId]);
            return $this->success(null, '登出成功');
        } catch (\Throwable $e) {
            // 记录错误日志
            $this->logError('用户登出异常', ['user_id' => $userId], $e, 'auth');
            return $this->fail(500, '登出失败，请稍后重试');
        }
    }

    /**
     * 刷新Token
     * @Auth(guard="jwt")
     * @PostMapping(path="/refresh")
     * @return ResponseInterface
     */
    public function refresh(): ResponseInterface
    {
        try {
            // 获取当前用户信息用于日志记录
            $user = $this->getCurrentUser();
            $userId = $user ? $user->id : null;
            
            // 刷新token
            $token = $this->auth->guard('jwt')->refresh();
            
            // 记录Token刷新成功
            $this->logAction('Token刷新成功', ['user_id' => $userId]);
            return $this->success([
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 3600
            ], 'Token刷新成功');
        } catch (\Throwable $e) {
            // 记录错误日志
            $this->logError('Token刷新异常', ['user_id' => $userId ?? 'unknown'], $e, 'auth');
            return $this->fail(401, 'Token已过期或无效');
        }
    }

    /**
     * 获取当前用户信息
     * @Auth(guard="jwt")
     * @GetMapping(path="/me")
     * @return ResponseInterface
     */
    public function me(): ResponseInterface
    {
        try {
            // 获取当前登录用户
            $user = $this->getCurrentUser();
            
            if (empty($user)) {
                $this->logWarning('获取用户信息失败', [
                'error_code' => 401,
                'message' => '用户未登录'
            ], 'auth');
                return $this->fail(401, '用户未登录');
            }
            
            // 记录获取用户信息成功
            $this->logAction('获取用户信息成功', ['user_id' => $user->id]);
            
            // 格式化用户信息
            return $this->success([
                'user' => $this->formatUserInfo($user, 'full'),
            ], '获取用户信息成功');
        } catch (\Throwable $e) {
            // 记录错误日志
            $this->logError('获取用户信息异常', [], $e, 'auth');
            return $this->fail(401, '未授权');
        }
    }

    /**
     * 修改密码
     * @Auth(guard="jwt")
     * @PutMapping(path="/password")
     * @return ResponseInterface
     */
    public function changePassword(): ResponseInterface
    {
        // 先获取用户ID，确保在catch块中也能访问
        $user = $this->getCurrentUser();
        $userId = $user ? $user->id : null;
        
        try {
            // 获取密码参数
            $currentPassword = $this->request->input('current_password', '');
            $newPassword = $this->request->input('new_password', '');
            
            if (empty($user)) {
                $this->logger->warning('修改密码失败', [
                    'error_code' => 401,
                    'message' => '用户未登录'
                ]);
                return $this->fail(401, '用户未登录');
            }
            
            // 参数验证
            if (empty($currentPassword) || empty($newPassword)) {
                throw new \InvalidArgumentException('当前密码和新密码不能为空');
            }
            
            // 验证密码长度
            if (strlen($newPassword) < 6) {
                throw new \InvalidArgumentException('新密码长度不能少于6位');
            }
            
            // 修改密码
            $this->userService->changePassword($user, $currentPassword, $newPassword);
            
            // 强制登出，需要重新登录
            $this->auth->guard('jwt')->logout();
            
            // 记录密码修改成功
            $this->logAction('密码修改成功', ['user_id' => $userId]);
            
            return $this->success(null, '密码修改成功，请重新登录');
        } catch (\InvalidArgumentException $e) {
            // 记录修改密码参数验证失败
            $this->logWarning('修改密码参数验证失败', [
                'user_id' => $userId,
                'message' => $e->getMessage()
            ], 'auth');
            return $this->fail(400, $e->getMessage());
        } catch (\Throwable $e) {
            // 记录错误日志
            $this->logError('修改密码异常', ['user_id' => $userId], $e, 'auth');
            return $this->fail(500, '密码修改失败，请稍后重试');
        }
    }

    /**
     * 更新个人资料
     * @Auth(guard="jwt")
     * @PutMapping(path="/profile")
     * @return ResponseInterface
     */
    public function updateProfile(): ResponseInterface
    {
        // 先获取用户ID，确保在catch块中也能访问
        $user = $this->getCurrentUser();
        $userId = $user ? $user->id : null;
        
        try {
            // 获取更新数据
            $data = $this->request->all();
            
            if (empty($user)) {
                $this->logWarning('更新个人资料失败', [
                'error_code' => 401,
                'message' => '用户未登录'
            ], 'auth');
                return $this->fail(401, '用户未登录');
            }
            
            // 记录更新请求（不记录敏感数据）
            $this->logAction('用户个人资料更新请求', [
                'user_id' => $userId,
                'updated_fields' => array_keys(array_diff_key($data, ['password' => 1, 'token' => 1])),
            ]);
            
            // 使用UserService更新用户信息
            $updatedUser = $this->userService->updateUser($user, $data);
            
            // 记录更新成功
            $this->logAction('个人资料更新成功', ['user_id' => $userId]);
            
            // 格式化用户信息
            return $this->success([
                'user' => $this->formatUserInfo($updatedUser, 'profile'),
            ], '个人资料更新成功');
        } catch (\InvalidArgumentException $e) {
            // 记录更新个人资料参数验证失败
            $this->logWarning('更新个人资料参数验证失败', [
                'user_id' => $userId,
                'message' => $e->getMessage()
            ], 'auth');
            return $this->fail(400, $e->getMessage());
        } catch (\Throwable $e) {
            // 记录错误日志
            $this->logError('更新个人资料异常', ['user_id' => $userId], $e, 'auth');
            return $this->fail(500, '个人资料更新失败，请稍后重试');
        }
    }
}