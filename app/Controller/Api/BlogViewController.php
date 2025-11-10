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

namespace App\Controller\Api;

use App\Constants\ResponseMessage;
use App\Constants\StatusCode;
use App\Controller\AbstractController;
use App\Controller\Api\Validator\BlogViewValidator;
use App\Service\BlogService;
use App\Traits\LogTrait;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Validation\ValidationException;
use Throwable;

/**
 * @Controller(prefix="api/blog")
 */
class BlogViewController extends AbstractController
{
    use LogTrait;

    /**
     * @Inject
     * @var BlogService
     */
    protected $blogService;

    /**
     * @Inject
     * @var BlogViewValidator
     */
    protected $blogViewValidator;

    /**
     * 记录博客阅读量.
     */
    /**
     * @RequestMapping(path="{id}/record-view", methods={"POST"})
     */
    public function recordView(int $id)
    {
        try {
            // 验证博客ID
            $this->blogViewValidator->validateBlogId($id);

            // 获取客户端IP
            $clientIp = $this->request->getServerParams()['REMOTE_ADDR'] ?? '';

            // 记录阅读量
            $result = $this->blogService->recordView($id, $clientIp);

            if (! $result) {
                return $this->fail(StatusCode::NOT_FOUND, '博客不存在');
            }

            return $this->success(['view_count' => $result], ResponseMessage::UPDATE_SUCCESS);
        } catch (ValidationException $e) {
            return $this->fail(StatusCode::VALIDATION_ERROR, $e->validator->errors()->first());
        } catch (Throwable $exception) {
            $this->logError('记录阅读量失败', ['blog_id' => $id, 'error' => $exception->getMessage()], $exception);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '记录阅读量失败');
        }
    }
}
