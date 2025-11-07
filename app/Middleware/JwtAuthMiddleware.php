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
 * 遵循hyperf-auth官方标准的认证中间件实现
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
        $this->logger = $this->loggerFactory->get('jwt');
        $this->role = $role;
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
                    'required_role' => $this->role,
                    'user_role' => $user->role ?? null
                ]);
                return $this->response->json([
                    'code' => StatusCode::FORBIDDEN,
                    'message' => '权限不足，需要' . $this->role . '权限',
                    'data' => null
                ])->withStatus(403);
            }
            
            // 将用户信息存储到上下文，便于后续使用
            Context::set('user', $user);
            Context::set('user_id', $user->id ?? null);
            
            // 认证成功，继续处理请求
            $this->logger->info('JWT认证成功', [
                'user_id' => $user->id ?? 'unknown',
                'role' => $this->role
            ]);
            return $handler->handle($request);
        } catch (UnauthorizedException $e) {
            // 捕获hyperf-auth特定的未授权异常
            $this->logger->warning('JWT认证未授权', ['error' => $e->getMessage()]);
            return $this->response->json([
                'code' => StatusCode::UNAUTHORIZED,
                'message' => '未授权，请先登录',
                'data' => null
            ])->withStatus(401);
        } catch (\Throwable $e) {
            $this->logger->error('JWT认证异常', [
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
     * @param \App\Model\User $user 用户对象
     * @param string $role 需要的角色
     * @return bool 用户是否具有指定角色
     */
    protected function checkUserRole($user, string $role): bool
    {
        return $user->role === $role;
    }
}