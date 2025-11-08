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
            
            // 查找用户
            $user = $this->userService->getUserByEmail($email);
            if (!$user) {
                $this->logWarning('用户不存在', ['email' => $email], 'auth');
                return $this->fail(401, '邮箱或密码错误');
            }
            
            // 验证密码
            if (!$this->userService->validateCredentials($user, $password)) {
                $this->logWarning('密码错误', ['email' => $email], 'auth');
                return $this->fail(401, '邮箱或密码错误');
            }
            
            // 创建一个简单的认证对象供JWT使用
            $authUser = (object)[
                'id' => $user['id'],
                'email' => $user['email'],
                'username' => $user['username'],
                'role' => $user['role'] ?? 'user',
                'status' => $user['status'] ?? 0
            ];
            
            // 使用JWT guard的login方法生成token
            $token = $this->auth->guard('jwt')->login($authUser);
            
            if (!$token) {
                $this->logWarning('登录失败', ['email' => $email], 'auth');
                return $this->fail(401, '邮箱或密码错误');
            }
            
            // 记录成功登录信息
            $this->logAction('用户登录成功', [
                'user_id' => $user['id'],
                'email' => $email,
                'user_role' => $user['role'] ?? 'user'
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
     * @PostMapping(path="/logout")
     * @return ResponseInterface
     */
    public function logout(): ResponseInterface
    {
        try {
            // 获取当前用户ID用于日志
            $user = $this->getCurrentUser();
            $userId = $user ? $user->id : null;
            
            // 使用AuthManager登出
            $this->auth->guard('jwt')->logout();
            
            // 记录登出日志
            $this->logAction('用户登出成功', ['user_id' => $userId]);
            
            // 返回成功响应
            return $this->success(null, '登出成功');
        } catch (\Throwable $e) {
            // 记录错误日志
            $this->logError('用户登出异常', ['user_id' => null], $e, 'auth');
            return $this->fail(500, '登出失败，请稍后重试');
        }
    }

    /**
     * 获取当前登录用户信息
     * @Auth(guard="jwt")
     * @GetMapping(path="/me")
     * @return ResponseInterface
     */
    public function me(): ResponseInterface
    {
        try {
            // 使用AuthManager获取当前用户
            $user = $this->getCurrentUser();
            
            if (empty($user)) {
                return $this->fail(401, '未授权，请先登录');
            }
            
            // 记录日志
            $this->logAction('获取用户信息成功', ['user_id' => $user->id]);
            
            // 返回用户信息
            return $this->success([
                'user' => $this->formatUserInfo($user, 'profile'),
            ], '获取成功');
        } catch (\Throwable $e) {
            // 记录错误日志
            $this->logError('获取用户信息异常', [], $e, 'auth');
            return $this->fail(500, '获取用户信息失败，请稍后重试');
        }
    }

    /**
     * 获取当前登录用户信息的辅助方法
     * @return mixed|null
     */
    protected function getCurrentUser()
    {
        return $this->auth->guard('jwt')->user();
    }

    /**
     * 刷新token
     * @Auth(guard="jwt")
     * @PostMapping(path="/refresh")
     * @return ResponseInterface
     */
    public function refreshToken(): ResponseInterface
    {
        try {
            // 使用AuthManager刷新token
            $newToken = $this->auth->guard('jwt')->refresh();
            
            // 获取当前用户ID用于日志
            $user = $this->getCurrentUser();
            $userId = $user ? $user->id : null;
            
            // 记录日志
            $this->logAction('Token刷新成功', ['user_id' => $userId]);
            
            // 返回新token
            return $this->success([
                'token' => $newToken,
            ], 'Token刷新成功');
        } catch (\Throwable $e) {
            // 记录错误日志
            $this->logError('Token刷新异常', [], $e, 'auth');
            return $this->fail(500, 'Token刷新失败，请稍后重试');
        }
    }

    /**
     * 格式化用户信息
     *
     * @param array $user 用户数组
     * @param string $type 信息类型: 'basic', 'profile', 'full'
     * @return array
     */
    protected function formatUserInfo($user, string $type = 'basic'): array
    {
        // 确保user是数组
        if (is_object($user)) {
            $user = $user->toArray();
        }
        
        $basicInfo = [
            'id' => $user['id'] ?? null,
            'username' => $user['username'] ?? null,
            'email' => $user['email'] ?? null,
            'status' => $user['status'] ?? null
        ];
        
        switch ($type) {
            case 'profile':
                $basicInfo += [
                    'real_name' => $user['real_name'] ?? null,
                    'avatar' => $user['avatar'] ?? null,
                    'bio' => $user['bio'] ?? null,
                    'role' => $user['role'] ?? null
                ];
                break;
            case 'full':
                $basicInfo += [
                    'real_name' => $user['real_name'] ?? null,
                    'avatar' => $user['avatar'] ?? null,
                    'bio' => $user['bio'] ?? null,
                    'role' => $user['role'] ?? null,
                    'created_at' => $user['created_at'] ?? null
                ];
                break;
        }
        
        return $basicInfo;
    }
}