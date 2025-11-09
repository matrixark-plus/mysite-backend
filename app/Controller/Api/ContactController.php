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

use App\Constants\StatusCode;
use App\Controller\AbstractController;
use App\Service\ContactService;
use App\Traits\LogTrait;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

/**
 * @Controller(prefix="/api/contact")
 */
class ContactController extends AbstractController
{
    use LogTrait;

    /**
     * @Inject
     * @var ContactService
     */
    protected $contactService;

    /**
     * 提交联系表单.
     *
     * @RequestMapping(path="/submit", methods={"POST"})
     */
    public function submitContact(RequestInterface $request)
    {
        try {
            $data = $request->all();

            // 获取客户端IP
            $data['ip'] = $request->getServerParams()['remote_addr'] ?? '';

            // 提交联系表单
            $result = $this->contactService->submitContactForm($data);

            if ($result['success']) {
                return $this->success(null, $result['message']);
            }
            return $this->fail(StatusCode::BAD_REQUEST, $result['message']);
        } catch (Exception $e) {
            $this->logError('提交联系表单异常', ['message' => $e->getMessage()], $e, 'contact');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '服务器内部错误');
        }
    }
}

/**
 * 联系表单管理控制器（管理员功能）
 * @Controller(prefix="/api/admin/contact-forms")
 */
class ContactFormAdminController extends AbstractController
{
    /**
     * @Inject
     * @var ContactService
     */
    protected $contactService;

    /**
     * 获取联系表单列表
     * @RequestMapping(path="", methods={"GET"})
     */
    public function getContactForms(RequestInterface $request)
    {
        // 权限验证：仅管理员可访问
        $currentUser = $this->user ?? null;
        if (! $currentUser || ! $currentUser->is_admin) {
            return $this->fail('无权访问', 403);
        }

        try {
            // 获取查询参数
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 20);
            $status = $request->input('status');
            $search = $request->input('search');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // 构建筛选条件
            $filters = [];
            if ($status !== null) {
                $filters['status'] = (int) $status;
            }
            if ($search) {
                $filters['search'] = $search;
            }
            if ($startDate || $endDate) {
                $filters['date_range'] = [];
                if ($startDate) {
                    $filters['date_range']['start'] = $startDate;
                }
                if ($endDate) {
                    $filters['date_range']['end'] = $endDate;
                }
            }

            // 获取联系表单列表
            $result = $this->contactService->getContactList($page, $limit, $filters);

            return $this->success([
                'items' => $result['list'],
                'meta' => [
                    'total' => $result['total'],
                    'page' => $result['page'],
                    'page_size' => $result['pageSize'],
                    'total_pages' => ceil($result['total'] / $result['pageSize'])
                ]
            ]);
        } catch (Exception $e) {
            $this->logger->error('获取联系表单列表异常: ' . $e->getMessage());
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '服务器内部错误');
        }
    }

    /**
     * 更新表单处理状态
     * @RequestMapping(path="/{id}/status", methods={"PUT"})
     */
    public function updateStatus(RequestInterface $request, $id)
    {
        // 权限验证：仅管理员可访问
        $currentUser = $this->user ?? null;
        if (! $currentUser || ! $currentUser->is_admin) {
            return $this->fail('无权访问', 403);
        }

        try {
            $data = $request->all();
            if (!isset($data['status'])) {
                return $this->fail(StatusCode::BAD_REQUEST, '状态参数不能为空');
            }

            // 更新状态
            $additionalData = [];
            if (isset($data['processed_by'])) {
                $additionalData['processed_by'] = $data['processed_by'];
            }

            $success = $this->contactService->markAsProcessed((int) $id, $additionalData);
            
            if ($success) {
                return $this->success(null, '状态更新成功');
            }
            return $this->fail('状态更新失败');
        } catch (Exception $e) {
            $this->logger->error('更新表单处理状态异常: ' . $e->getMessage(), ['id' => $id]);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '服务器内部错误');
        }
    }

    /**
     * 删除联系表单
     * @RequestMapping(path="/{id}", methods={"DELETE"})
     */
    public function deleteContactForm(RequestInterface $request, $id)
    {
        // 权限验证：仅管理员可访问
        $currentUser = $this->user ?? null;
        if (! $currentUser || ! $currentUser->is_admin) {
            return $this->fail('无权访问', 403);
        }

        try {
            $result = $this->contactService->deleteContactForm((int) $id);
            
            if ($result['success']) {
                return $this->success(null, $result['message']);
            }
            return $this->fail($result['message']);
        } catch (Exception $e) {
            $this->logger->error('删除联系表单异常: ' . $e->getMessage(), ['id' => $id]);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '服务器内部错误');
        }
    }
}
