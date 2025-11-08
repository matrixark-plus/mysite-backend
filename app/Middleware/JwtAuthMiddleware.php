<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Middleware;

use App\Constants\StatusCode;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Logger\LoggerFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Qbhy\HyperfAuth\AuthManager;
use Qbhy\HyperfAuth\Exception\UnauthorizedException;
use Throwable;

/**
 * JWT认证中间件
 * 实现基于角色的简单权限控制.
 */
class JwtAuthMiddleware implements MiddlewareInterface
{
    /**
     * @var HttpResponse
     */
    protected $response;

    /**
     * @var LoggerFactory
     */
    protected $loggerFactory;

    /**
     * @var AuthManager
     */
    protected $auth;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 角色参数.
     */
    protected ?string $role;

    /**
     * 认证守卫名称.
     */
    protected string $guard = 'jwt';

    /**
     * JwtAuthMiddleware constructor.
     * @param null|string $role 需要验证的角色
     */
    public function __construct(?string $role = null)
    {
        // 构造函数中只设置角色，logger将在第一次使用时初始化
        $this->role = $role;
    }

    /**
     * @Inject
     */
    public function setResponse(HttpResponse $response)
    {
        $this->response = $response;
    }

    /**
     * @Inject
     */
    public function setLoggerFactory(LoggerFactory $loggerFactory)
    {
        $this->loggerFactory = $loggerFactory;
    }

    /**
     * @Inject
     */
    public function setAuth(AuthManager $auth)
    {
        $this->auth = $auth;
    }

    /**
     * 处理请求，验证JWT令牌.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // 使用指定的guard获取当前认证用户
            $user = $this->auth->guard($this->guard)->user();

            // 检查用户是否已认证
            if (! $user) {
                $this->getLogger()?->warning('JWT认证失败: 用户未认证');
                return $this->response->json([
                    'code' => StatusCode::UNAUTHORIZED,
                    'message' => '未授权，请先登录',
                    'data' => null,
                ])->withStatus(401);
            }

            // 如果需要验证角色，直接传入用户对象
            if ($this->role && ! $this->checkUserRole($user, $this->role)) {
                $userId = $user->id ?? 'unknown';
                $userRole = $user->role ?? null;

                $this->getLogger()?->warning('JWT角色验证失败', [
                    'user_id' => $userId,
                    'required_role' => $this->role,
                    'user_role' => $userRole,
                ]);
                return $this->response->json([
                    'code' => StatusCode::FORBIDDEN,
                    'message' => '权限不足，需要' . $this->role . '权限',
                    'data' => null,
                ])->withStatus(403);
            }

            // 存储用户对象信息
            Context::set('user', $user); // 存储原始用户对象
            Context::set('user_id', $user->id ?? null);
            Context::set('user_role', $user->role ?? null);

            // 认证成功，继续处理请求
            $this->getLogger()?->info('JWT认证成功', [
                'user_id' => $user->id ?? 'unknown',
                'role' => $this->role,
                'user_type' => get_class($user),
            ]);
            return $handler->handle($request);
        } catch (UnauthorizedException $e) {
            // 捕获hyperf-auth特定的未授权异常
            $this->getLogger()?->warning('JWT认证未授权', ['error' => $e->getMessage()]);
            return $this->response->json([
                'code' => StatusCode::UNAUTHORIZED,
                'message' => '未授权，请先登录',
                'data' => null,
            ])->withStatus(401);
        } catch (Throwable $e) {
            $this->getLogger()?->error('JWT认证异常', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 200), // 限制日志长度
            ]);
            return $this->response->json([
                'code' => StatusCode::UNAUTHORIZED,
                'message' => '认证失败，请重新登录',
                'data' => null,
            ])->withStatus(401);
        }
    }

    /**
     * 获取或初始化logger.
     * @return LoggerInterface
     */
    protected function getLogger()
    {
        if (! isset($this->logger) && $this->loggerFactory) {
            $this->logger = $this->loggerFactory->get('jwt');
        }
        return $this->logger;
    }

    /**
     * 检查用户角色.
     * @param array $user 用户信息
     * @param string $role 角色名称
     */
    protected function checkUserRole(object $user, string $role): bool
    {
        // 获取用户角色
        $userRole = $user->role ?? null;

        // 管理员拥有所有权限
        if ($userRole === 'admin') {
            return true;
        }

        // 默认情况下进行字符串比较
        return $userRole === $role;
    }
}
