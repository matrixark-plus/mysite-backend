<?php

declare(strict_types=1);
/**
 * 博客阅读量控制器
 * 处理博客阅读量记录相关功能
 */

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Constants\ResponseMessage;
use App\Constants\StatusCode;
use App\Service\BlogService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\RequestMethod;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Di\Annotation\Inject;

/**
 * @Controller(prefix="api/blog")
 */
class BlogViewController extends AbstractController
{
    /**
     * @Inject
     * @var BlogService
     */
    protected $blogService;

    /**
     * 记录博客阅读量
     * @return ResponseInterface
     */
    /**
     * @RequestMapping(path="{id}/record-view", methods={"POST"})
     */
    public function recordView(int $id): ResponseInterface
    {
        try {
            // 获取博客ID
            if (empty($id)) {
                return $this->fail(StatusCode::VALIDATION_ERROR, ResponseMessage::PARAM_REQUIRED);
            }

            // 获取客户端IP
            $clientIp = $this->request->getServerParams()['REMOTE_ADDR'] ?? '';
            
            // 记录阅读量
            $result = $this->blogService->recordView($id, $clientIp);
            
            if (! $result) {
                return $this->fail(StatusCode::NOT_FOUND, '博客不存在');
            }

            return $this->success(['view_count' => $result], ResponseMessage::UPDATE_SUCCESS);
        } catch (\Throwable $exception) {
            $this->logError('记录阅读量失败', ['blog_id' => $id, 'error' => $exception->getMessage()], $exception);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '记录阅读量失败');
        }
    }
}