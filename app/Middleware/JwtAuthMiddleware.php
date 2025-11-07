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
use App\Service\UserService;

/**
 * JWT认证中间件
 * 用于验证API请求的JWT令牌和用户角色
 */
class JwtAuthMiddleware implements MiddlewareInterface
{
    /**
     * @Inject
     */
    protected HttpResponse $response;

    /**
     * @Inject
     */
    protected LoggerFactory $loggerFactory;

    /**
     * @Inject
     */
    protected AuthManager $auth;

    /**
     * @Inject
     */
    protected UserService $userService;

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
     * JwtAuthMiddleware constructor.
     * @param string|null $role 需要验证的角色
     */
    public function __construct(?string $role = null)
    {
        $this->logger = $this->loggerFactory->get('jwt');
        $this->role = $role;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // 尝试获取当前认证用户
            $user = $this->auth->user();
            
            // 检查用户是否已认证
            if (!$user) {
                $this->logger->warning('JWT认证失败: 用户未认证');
                return $this->response->json([
                    'code' => StatusCode::UNAUTHORIZED,
                    'message' => '未授权，请先登录',
                    'data' => null
                ])->withStatus(401);
            }
            
            // 如果需要验证角色
            if ($this->role && !$this->checkUserRole($user, $this->role)) {
                $this->logger->warning('JWT角色验证失败', [
                    'user_id' => $user->id ?? 'unknown',
                    'required_role' => $this->role
                ]);
                return $this->response->json([
                    'code' => StatusCode::FORBIDDEN,
                    'message' => '权限不足，需要' . $this->role . '权限',
                    'data' => null
                ])->withStatus(403);
            }
            
            // 将用户信息存储到上下文，便于后续使用
            Context::set('user', $user);
            
            // 认证成功，继续处理请求
            $this->logger->info('JWT认证成功', [
                'user_id' => $user->id ?? 'unknown',
                'role' => $this->role
            ]);
            return $handler->handle($request);
        } catch (\Throwable $e) {
            $this->logger->error('JWT认证异常: ' . $e->getMessage());
            return $this->response->json([
                'code' => StatusCode::UNAUTHORIZED,
                'message' => '认证失败: ' . $e->getMessage(),
                'data' => null
            ])->withStatus(401);
        }
    }

    /**
     * 检查用户角色
     * @param \App\Model\User $user 用户对象
     * @param string $role 需要的角色
     * @return bool 用户是否具有指定角色
     */
    protected function checkUserRole(\App\Model\User $user, string $role): bool
    {
        // 使用UserService的isAdmin()方法检查用户是否为管理员
        if ($this->userService->isAdmin($user)) {
            return true; // 管理员拥有所有权限
        }
        
        // 检查用户角色是否匹配
        return $user->role === $role;
    }
}