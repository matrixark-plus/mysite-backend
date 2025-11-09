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
use App\Controller\Api\Validator\AuthValidator;
use App\Model\User;
use App\Service\UserService;
use App\Traits\LogTrait;
use Hyperf\Config\Config;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\RequestMethod;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Validation\ValidationException;
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
     * @Inject
     * @var AuthValidator
     */
    protected $validator;

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

            // 使用验证器验证参数
            try {
                $this->validator->validateRegister([
                    'username' => $username,
                    'email' => $email,
                    'password' => $password,
                    'real_name' => $realName
                ]);
            } catch (ValidationException $e) {
                return $this->validationError($e->validator->errors()->first());
            }

            // 检查邮箱是否已存在
            if ($this->userService->getUserByEmail($email)) {
                return $this->error(ResponseMessage::DATA_EXISTS, []);
            }

            // 创建用户
            $userData = [
                'email' => $email,
                'real_name' => $realName,
                'is_active' => true, // 默认激活状态
                'password' => $password
            ];

            $userData = $this->userService->createUser($userData);
            // 直接从数组中获取用户ID
            $userId = $userData['id'] ?? 0;

            // 记录日志
            $this->logAction('用户注册成功', ['user_id' => $userId, 'email' => $email]);

            return $this->success(['user_id' => $userId, 'email' => $email], ResponseMessage::CREATE_SUCCESS);
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
            $email = (string) $this->request->input('email', '');
            $password = (string) $this->request->input('password', '');
            // 获取用户IP地址
            $loginIp = $this->request->getServerParams()['REMOTE_ADDR'] ?? '';

            // 使用验证器验证参数
            try {
                $this->validator->validateLogin([
                    'email' => $email,
                    'password' => $password
                ]);
            } catch (ValidationException $e) {
                return $this->validationError($e->validator->errors()->first());
            }

            // 使用userService处理登录逻辑，传入IP信息
            $userData = $this->userService->login($email, $password, $loginIp);
            
            // 由于auth组件需要User对象进行登录，这里仍需创建User对象
            // 但仅用于认证组件，不进行其他数据库操作
            $user = new User();
            $user->fill($userData);

            // 使用auth组件登录并生成token
            $token = (string) $this->auth->login($user);
            
            // 获取token过期时间
            $tokenTtl = (int) $this->config->get('auth.guards.jwt.ttl', 60 * 60 * 24);
            $expiresIn = time() + $tokenTtl;
            // 直接从数组中获取用户ID
            $userId = $userData['id'] ?? 0;

            // 记录登录日志
            $this->logAction('用户登录成功', ['user_id' => $userId, 'email' => $email, 'ip' => $loginIp]);

            // 返回登录成功响应
            return $this->success([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $expiresIn,
                'user' => $this->formatUserInfo($user)
            ], ResponseMessage::LOGIN_SUCCESS);
        } catch (\Throwable $exception) {
            // 处理账号锁定异常
            if (strpos($exception->getMessage(), '账号已被锁定') !== false) {
                $this->logWarning('账号锁定异常', ['error' => $exception->getMessage()]);
                return $this->fail(StatusCode::FORBIDDEN, $exception->getMessage());
            }
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
                return $this->unauthorized(ResponseMessage::LOGIN_REQUIRED);
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
                return $this->unauthorized(ResponseMessage::LOGIN_REQUIRED);
            }

            // 对于已认证用户，直接从认证组件获取ID
            $userId = $user->getId();
            
            // 通过UserService获取完整用户信息
            $userData = $this->userService->getUserById($userId);
            if (! $userData) {
                return $this->notFound(ResponseMessage::RESOURCE_NOT_FOUND);
            }

            // 记录日志
            $this->logAction('获取用户信息成功', ['user_id' => $userId]);

            return $this->success(
                ['user' => $this->formatUserInfo($userData)],
                ResponseMessage::QUERY_SUCCESS
            );
        } catch (\Throwable $exception) {
            $this->logError('获取用户信息失败', ['error' => $exception->getMessage()], $exception);
            return $this->error('获取用户信息失败');
        }
    }

    /**
     * 格式化用户信息
     * @param array $userData 用户数据数组
     * @return array 格式化后的用户信息数组
     */
    protected function formatUserInfo(array $userData): array
    {
        return [
            'id' => $userData['id'] ?? null,
            'email' => $userData['email'] ?? null,
            'real_name' => $userData['real_name'] ?? null,
            'avatar' => $userData['avatar'] ?? null,
            'bio' => $userData['bio'] ?? null,
            'is_admin' => $userData['is_admin'] ?? null,
            'is_active' => $userData['is_active'] ?? null,
            'created_at' => $userData['created_at'] ?? null,
        ];
    }
}