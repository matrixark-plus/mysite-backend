<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Constants\StatusCode;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qbhy\HyperfAuth\AuthManager;
use Qbhy\HyperfAuth\Exception\UnauthorizedException;

/**
 * JWT认证中间件
 * 实现基于角色的简单权限控制
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
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * 角色参数
     * @var string|null
     */
    protected ?string $role;

    /**
     * 认证守卫名称
     * @var string
     */
    protected string $guard = 'jwt';

    /**
     * JwtAuthMiddleware constructor.
     * @param string|null $role 需要验证的角色
     */
    public function __construct(?string $role = null)
    {
        // 构造函数中只设置角色，logger将在第一次使用时初始化
        $this->role = $role;
    }

    /**
     * 获取或初始化logger
     * @return \Psr\Log\LoggerInterface
     */
    protected function getLogger()
    {
        if (!isset($this->logger) && $this->loggerFactory) {
            $this->logger = $this->loggerFactory->get('jwt');
        }
        return $this->logger;
    }

    /**
     * 处理请求，验证JWT令牌
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // 使用指定的guard获取当前认证用户
            $user = $this->auth->guard($this->guard)->user();
            
            // 检查用户是否已认证
            if (!$user) {
                $this->getLogger()?->warning('JWT认证失败: 用户未认证');
                return $this->response->json([
                    'code' => StatusCode::UNAUTHORIZED,
                    'message' => '未授权，请先登录',
                    'data' => null
                ])->withStatus(401);
            }
            
            // 将用户信息统一转换为数组格式
            $userArray = is_object($user) ? $user->toArray() : $user;
            
            // 如果需要验证角色，确保传入checkUserRole的是数组
            if ($this->role && !$this->checkUserRole($userArray, $this->role)) {
                $userId = $userArray['id'] ?? 'unknown';
                $userRole = $userArray['role'] ?? null;
                
                $this->getLogger()?->warning('JWT角色验证失败', [
                    'user_id' => $userId,
                    'required_role' => $this->role,
                    'user_role' => $userRole
                ]);
                return $this->response->json([
                    'code' => StatusCode::FORBIDDEN,
                    'message' => '权限不足，需要' . $this->role . '权限',
                    'data' => null
                ])->withStatus(403);
            }
            
            // 将用户信息存储到上下文，便于后续使用
            Context::set('user', $userArray);
            Context::set('user_id', $userArray['id'] ?? null);
            Context::set('user_role', $userArray['role'] ?? null);
            
            // 认证成功，继续处理请求
            $this->getLogger()?->info('JWT认证成功', [
                'user_id' => $userArray['id'] ?? 'unknown',
                'role' => $this->role
            ]);
            return $handler->handle($request);
        } catch (UnauthorizedException $e) {
            // 捕获hyperf-auth特定的未授权异常
            $this->getLogger()?->warning('JWT认证未授权', ['error' => $e->getMessage()]);
            return $this->response->json([
                'code' => StatusCode::UNAUTHORIZED,
                'message' => '未授权，请先登录',
                'data' => null
            ])->withStatus(401);
        } catch (\Throwable $e) {
            $this->getLogger()?->error('JWT认证异常', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 200) // 限制日志长度
            ]);
            return $this->response->json([
                'code' => StatusCode::UNAUTHORIZED,
                'message' => '认证失败，请重新登录',
                'data' => null
            ])->withStatus(401);
        }
    }

    /**
     * 检查用户角色
     * @param array $user 用户信息
     * @param string $role 角色名称
     * @return bool
     */
    protected function checkUserRole(array $user, string $role): bool
    {
        // 获取用户角色
        $userRole = $user['role'] ?? null;
        
        // 如果用户角色是数组，检查数组中是否包含指定角色
        if (is_array($userRole)) {
            return in_array($role, $userRole, true);
        }
        
        // 默认情况下进行字符串比较
        return $userRole === $role;
    }
}