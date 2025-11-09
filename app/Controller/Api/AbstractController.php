\u003c?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller\Api;

use Hyperf\Context\Context;
use Hyperf\Contract\ContainerInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Di\Annotation\Inject;

/**
 * API控制器基类
 * 提供通用的响应方法和工具函数.
 */
abstract class AbstractController
{
    /**
     * @Inject
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @Inject
     * @var RequestInterface
     */
    protected $request;

    /**
     * @Inject
     * @var ResponseInterface
     */
    protected $response;

    /**
     * 获取请求对象
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * 获取响应对象
     *
     * @return ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * 成功响应
     *
     * @param string $message 成功消息
     * @param array $data 响应数据
     * @param int $statusCode HTTP状态码
     * @return ResponseInterface
     */
    protected function success(string $message = 'success', array $data = [], int $statusCode = 200): ResponseInterface
    {
        return $this->response->json([
            'code' => $statusCode,
            'message' => $message,
            'data' => $data,
        ])->withStatus($statusCode);
    }

    /**
     * 错误响应
     *
     * @param string $message 错误消息
     * @param array $data 响应数据
     * @param int $statusCode HTTP状态码
     * @return ResponseInterface
     */
    protected function error(string $message = 'error', array $data = [], int $statusCode = 400): ResponseInterface
    {
        return $this->response->json([
            'code' => $statusCode,
            'message' => $message,
            'data' => $data,
        ])->withStatus($statusCode);
    }

    /**
     * 服务器错误响应
     *
     * @param string $message 错误消息
     * @return ResponseInterface
     */
    protected function serverError(string $message = 'Internal Server Error'): ResponseInterface
    {
        return $this->response->json([
            'code' => 500,
            'message' => $message,
            'data' => [],
        ])->withStatus(500);
    }

    /**
     * 未授权响应
     *
     * @param string $message 错误消息
     * @return ResponseInterface
     */
    protected function unauthorized(string $message = 'Unauthorized'): ResponseInterface
    {
        return $this->response->json([
            'code' => 401,
            'message' => $message,
            'data' => [],
        ])->withStatus(401);
    }

    /**
     * 禁止访问响应
     *
     * @param string $message 错误消息
     * @return ResponseInterface
     */
    protected function forbidden(string $message = 'Forbidden'): ResponseInterface
    {
        return $this->response->json([
            'code' => 403,
            'message' => $message,
            'data' => [],
        ])->withStatus(403);
    }

    /**
     * 资源不存在响应
     *
     * @param string $message 错误消息
     * @return ResponseInterface
     */
    protected function notFound(string $message = 'Resource Not Found'): ResponseInterface
    {
        return $this->response->json([
            'code' => 404,
            'message' => $message,
            'data' => [],
        ])->withStatus(404);
    }

    /**
     * 验证失败响应
     *
     * @param string $message 错误消息
     * @param array $errors 错误详情
     * @return ResponseInterface
     */
    protected function validationError(string $message = 'Validation Failed', array $errors = []): ResponseInterface
    {
        return $this->response->json([
            'code' => 422,
            'message' => $message,
            'data' => $errors,
        ])->withStatus(422);
    }

    /**
     * 获取当前上下文的用户ID
     * 实际项目中应该从JWT token或session中获取
     *
     * @return int|null
     */
    protected function getUserIdFromContext(): ?int
    {
        // 这里只是一个示例，实际实现应该从认证中间件设置的上下文或请求中获取
        return Context::get('user.id');
    }

    /**
     * 分页参数处理
     *
     * @param int $defaultPage 默认页码
     * @param int $defaultLimit 默认每页数量
     * @param int $maxLimit 最大每页数量
     * @return array [page, limit]
     */
    protected function handlePagination(int $defaultPage = 1, int $defaultLimit = 20, int $maxLimit = 100): array
    {
        $page = max(1, (int)($this->request->input('page', $defaultPage) ?: $defaultPage));
        $limit = max(1, min($maxLimit, (int)($this->request->input('limit', $defaultLimit) ?: $defaultLimit)));
        
        return [$page, $limit];
    }
}