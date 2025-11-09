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

use App\Traits\ResponseTrait;
use App\Constants\ResponseMessage;
use App\Constants\StatusCode;
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
    use ResponseTrait;
    
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
     * 服务器错误响应
     *
     * @param string $message 错误消息
     * @return ResponseInterface
     */
    protected function serverError(string $message = 'Internal Server Error'): ResponseInterface
    {
        return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, $message);
    }

    /**
     * 未授权响应
     *
     * @param string $message 错误消息
     * @return ResponseInterface
     */
    protected function unauthorized(string $message = 'Unauthorized'): ResponseInterface
    {
        return $this->fail(StatusCode::UNAUTHORIZED, $message);
    }

    /**
     * 禁止访问响应
     *
     * @param string $message 错误消息
     * @return ResponseInterface
     */
    protected function forbidden(string $message = 'Forbidden'): ResponseInterface
    {
        return $this->fail(StatusCode::FORBIDDEN, $message);
    }

    /**
     * 资源不存在响应
     *
     * @param string $message 错误消息
     * @return ResponseInterface
     */
    protected function notFound(string $message = 'Resource Not Found'): ResponseInterface
    {
        return $this->fail(StatusCode::NOT_FOUND, $message);
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
        return $this->fail(StatusCode::VALIDATION_ERROR, $message, $errors);
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