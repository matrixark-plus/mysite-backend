<?php

declare(strict_types=1);
/**
 * 认证控制器
 * 基于Hyperf Auth组件实现认证功能
 */

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Constants\ResponseMessage;
use App\Constants\StatusCode;
use App\Model\User;
use App\Service\UserService;
use App\Traits\LogTrait;
use Hyperf\Config\Config;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\RequestMethod;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Qbhy\HyperfAuth\AuthManager;
use Qbhy\HyperfAuth\Authenticatable;

/**
 * 认证控制器
 * 负责用户注册、登录、登出、Token刷新和用户信息获取
 */
/**
 * @Controller(prefix="api/auth")
 */
class AuthController extends AbstractController
{
    use LogTrait;

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
     * @var Config
     */
    protected $config;

    /**
     * 用户注册
     * @return ResponseInterface
     */
    /**
     * @RequestMapping(path="register", methods={"POST"})
     */
    public function register(): ResponseInterface
    {
        try {
            // 获取请求参数
            $username = (string) $this->request->input('username', '');
            $email = (string) $this->request->input('email', '');
            $password = (string) $this->request->input('password', '');
            $realName = (string) $this->request->input('real_name', '');

            // 参数验证
            if (empty($username) || empty($email) || empty($password)) {
                return $this->fail(StatusCode::VALIDATION_ERROR, ResponseMessage::PARAM_REQUIRED);
            }

            // 检查用户名或邮箱是否已存在
            if ($this->userService->getUserByUsername($username) || $this->userService->getUserByEmail($email)) {
                return $this->fail(StatusCode::DATA_EXISTS, ResponseMessage::DATA_EXISTS);
            }

            // 创建用户
            $userData = [
                'username' => $username,
                'email' => $email,
                'real_name' => $realName,
                'status' => 1, // 默认激活状态
                'password' => $password
            ];

            $userData = $this->userService->createUser($userData);
            // 由于UserService返回的是数组，我们需要转换回User对象
            $user = User::find($userData['id']);
            // 使用getId()方法代替getAuthIdentifier()，符合Authenticatable接口定义
            $userId = $user->getId();

            // 记录日志
            $this->logAction('用户注册成功', ['user_id' => $userId, 'username' => $username]);

            return $this->success(['user_id' => $userId, 'username' => $username], ResponseMessage::CREATE_SUCCESS);
        } catch (\Throwable $exception) {
            $this->logError('用户注册失败', ['error' => $exception->getMessage()], $exception);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '注册失败');
        }
    }

    /**
     * 用户登录
     * @return ResponseInterface
     */
    /**
     * @RequestMapping(path="login", methods={"POST"})
     */
    public function login(): ResponseInterface
    {
        try {
            // 获取登录凭证
            $username = (string) $this->request->input('username', '');
            $password = (string) $this->request->input('password', '');

            // 参数验证
            if (empty($username) || empty($password)) {
                return $this->fail(StatusCode::VALIDATION_ERROR, ResponseMessage::PARAM_REQUIRED);
            }

            // 查找用户
            $userData = $this->userService->getUserByUsername($username);
            if (! $userData) {
                return $this->fail(StatusCode::NOT_FOUND, ResponseMessage::RESOURCE_NOT_FOUND);
            }
            // 将数组转换为User对象
            $user = User::find($userData['id']);

            // 检查用户状态
            if (! $user || $user->status !== 1) {
                return $this->fail(StatusCode::FORBIDDEN, '用户账号已被禁用');
            }

            // 验证密码
            if (! $user->validatePassword($password)) {
                $this->logWarning('密码验证失败', ['username' => $username]);
                return $this->fail(StatusCode::UNAUTHORIZED, '密码错误');
            }

            // 使用auth组件登录并生成token
            $token = (string) $this->auth->login($user);
            
            // 获取token过期时间
            $tokenTtl = (int) $this->config->get('auth.guards.jwt.ttl', 60 * 60 * 24);
            $expiresIn = time() + $tokenTtl;
            // 使用getId()方法代替getAuthIdentifier()，符合Authenticatable接口定义
            $userId = $user->getId();

            // 记录登录日志
            $this->logAction('用户登录成功', ['user_id' => $userId, 'username' => $username]);

            // 返回登录成功响应
            return $this->success([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $expiresIn,
                'user' => $this->formatUserInfo($user)
            ], ResponseMessage::LOGIN_SUCCESS);
        } catch (\Throwable $exception) {
            $this->logError('用户登录失败', ['error' => $exception->getMessage()], $exception);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '登录失败');
        }
    }

    /**
     * 刷新Token
     * @return ResponseInterface
     */
    /**
     * @RequestMapping(path="refresh", methods={"POST"})
     * @Middleware(middleware=\App\Middleware\JwtAuthMiddleware::class)
     */
    public function refresh(): ResponseInterface
    {
        try {
            // 获取当前用户
            $user = $this->auth->user();
            if (! $user instanceof Authenticatable) {
                return $this->fail(StatusCode::UNAUTHORIZED, ResponseMessage::LOGIN_REQUIRED);
            }

            // 刷新token
            $token = (string) $this->auth->refresh();
            
            // 获取新token过期时间
            $tokenTtl = (int) $this->config->get('auth.guards.jwt.ttl', 60 * 60 * 24);
            $expiresIn = time() + $tokenTtl;
            // 使用getId()方法代替getAuthIdentifier()，符合Authenticatable接口定义
            $userId = $user->getId();

            // 记录日志
            $this->logAction('Token刷新成功', ['user_id' => $userId]);

            return $this->success([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $expiresIn
            ], ResponseMessage::UPDATE_SUCCESS);
        } catch (\Throwable $exception) {
            $this->logError('Token刷新失败', ['error' => $exception->getMessage()], $exception);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, 'Token刷新失败');
        }
    }

    /**
     * 用户登出
     * @return ResponseInterface
     */
    /**
     * @RequestMapping(path="logout", methods={"POST"})
     * @Middleware(middleware=\App\Middleware\JwtAuthMiddleware::class)
     */
    public function logout(): ResponseInterface
    {
        try {
            // 获取当前用户信息用于日志记录
            $user = $this->auth->user();
            // 使用getId()方法代替getAuthIdentifier()，符合Authenticatable接口定义
            $userId = $user instanceof Authenticatable ? $user->getId() : null;

            // 登出，使token失效
            $this->auth->logout();

            // 记录日志
            $this->logAction('用户登出成功', ['user_id' => $userId]);

            return $this->success(null, ResponseMessage::LOGOUT_SUCCESS);
        } catch (\Throwable $exception) {
            $this->logError('用户登出失败', ['error' => $exception->getMessage()], $exception);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '登出失败');
        }
    }

    /**
     * 获取当前用户信息
     * @return ResponseInterface
     */
    /**
     * @RequestMapping(path="me", methods={"GET"})
     * @Middleware(middleware=\App\Middleware\JwtAuthMiddleware::class)
     */
    public function me(): ResponseInterface
    {
        try {
            // 获取当前用户
            $user = $this->auth->user();
            if (! $user instanceof Authenticatable) {
                return $this->fail(StatusCode::UNAUTHORIZED, ResponseMessage::LOGIN_REQUIRED);
            }

            // 确保是User实例
            if (! $user instanceof User) {
                $user = $this->getUserInfo($user);
                if (! $user instanceof User) {
                    return $this->fail(StatusCode::NOT_FOUND, ResponseMessage::RESOURCE_NOT_FOUND);
                }
            }

            // 记录日志
            // 使用getId()方法代替getAuthIdentifier()，符合Authenticatable接口定义
            $this->logAction('获取用户信息成功', ['user_id' => $user->getId()]);

            return $this->success(
                ['user' => $this->formatUserInfo($user)],
                ResponseMessage::QUERY_SUCCESS
            );
        } catch (\Throwable $exception) {
            $this->logError('获取用户信息失败', ['error' => $exception->getMessage()], $exception);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取用户信息失败');
        }
    }

    /**
     * 格式化用户信息
     * @param User $user 用户对象
     * @return array 格式化后的用户信息数组
     */
    protected function formatUserInfo(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->username,
            'email' => $user->email,
            'real_name' => $user->real_name,
            'avatar' => $user->avatar,
            'bio' => $user->bio,
            'role' => $user->role,
            'status' => $user->status,
            'created_at' => $user->created_at,
        ];
    }

    /**
     * 根据认证对象获取完整的用户信息
     * @param Authenticatable $authUser 认证用户对象
     * @return User|null 用户对象或null
     */
    protected function getUserInfo(Authenticatable $authUser): ?User
    {
        // 使用接口中实际定义的getId()方法，而不是getAuthIdentifier()
        $userId = $authUser->getId();
        return User::find($userId);
    }
}