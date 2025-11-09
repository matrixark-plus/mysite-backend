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

use App\Controller\AbstractController;
use App\Service\ContactFormService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

/**
 * 联系表单控制器
 * 处理联系表单相关的HTTP请求.
 */
#[Controller]
#[RequestMapping(prefix: '/api/contact-forms')]
class ContactFormController extends AbstractController
{
    /**
     * @Inject
     * @var ContactFormService
     */
    protected $contactFormService;

    /**
     * 提交联系表单
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'submit', methods: ['POST'])]
    public function submitForm(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->all();
        
        $formData = [
            'name' => $data['name'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'subject' => $data['subject'] ?? '',
            'message' => $data['message'] ?? '',
            'source' => $data['source'] ?? 'website',
            'user_id' => $this->user?->id ?? null,
        ];

        $result = $this->contactFormService->createContactForm($formData);
        
        if ($result['success']) {
            return $this->success($result['message'], $result['data'] ?? []);
        }
        
        return $this->error($result['message'], []);
    }

    /**
     * 获取联系表单列表（管理员功能）
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[RequestMapping(path: '', methods: ['GET'])]
    public function getForms(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // 权限验证：仅管理员可访问
        $currentUser = $this->user ?? null;
        if (! $currentUser || ! $currentUser->is_admin) {
            return $this->fail(403, '无权访问');
        }

        $conditions = [];
        
        // 处理查询条件
        if ($status = $request->input('status')) {
            $conditions['status'] = $status;
        }
        
        if ($search = $request->input('search')) {
            $conditions['search'] = $search;
        }
        
        if ($startDate = $request->input('start_date')) {
            $conditions['created_at'] = ['$gte' => $startDate];
        }
        
        if ($endDate = $request->input('end_date')) {
            if (! isset($conditions['created_at'])) {
                $conditions['created_at'] = [];
            }
            $conditions['created_at']['$lte'] = $endDate;
        }

        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 20);
        $order = ['created_at' => 'desc'];

        $result = $this->contactFormService->getContactForms($conditions, $order, $page, $limit);
        
        if ($result['success']) {
            return $this->success($result['data']);
        }
        
        return $this->fail(400, $result['message']);
    }

    /**
     * 获取单个联系表单详情（管理员功能）
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[RequestMapping(path: '{id}', methods: ['GET'])]
    public function getFormDetail(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // 权限验证：仅管理员可访问
        $currentUser = $this->user ?? null;
        if (! $currentUser || ! $currentUser->is_admin) {
            return $this->fail(403, '无权访问');
        }

        $id = (int) $request->input('id');
        
        $result = $this->contactFormService->getContactFormDetail($id);
        
        if ($result['success']) {
            return $this->success($result['data']);
        }
        
        return $this->fail(400, $result['message']);
    }

    /**
     * 标记表单为已处理（管理员功能）
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[RequestMapping(path: '{id}/mark-processed', methods: ['POST'])]
    public function markAsProcessed(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // 权限验证：仅管理员可访问
        $currentUser = $this->user ?? null;
        if (! $currentUser || ! $currentUser->is_admin) {
            return $this->fail(403, '无权访问');
        }

        $id = (int) $request->input('id');
        $processorNote = $request->input('processor_note', '');
        
        $result = $this->contactFormService->markAsProcessed($id, $processorNote);
        
        if ($result['success']) {
            return $this->success([], $result['message']);
        }
        
        return $this->validationError($result['message']);
    }
}