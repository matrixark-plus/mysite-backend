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

namespace App\Service;

use App\Repository\ContactFormRepository;
use Exception;
use Hyperf\Di\Annotation\Inject;

/**
 * 联系表单服务层
 * 处理联系表单相关的业务逻辑.
 */
class ContactFormService extends BaseService
{
    /**
     * @Inject
     * @var ContactFormRepository
     */
    protected $contactFormRepository;

    /**
     * @Inject
     * @var MailService
     */
    protected $mailService;

    /**
     * 创建联系表单.
     *
     * @param array<string, mixed> $formData 表单数据
     * @return array 操作结果
     */
    public function createContactForm(array $formData): array
    {
        return $this->executeWithErrorHandling(function () use ($formData) {
            // 验证必要字段
            $requiredFields = ['name', 'email', 'subject', 'message'];
            $errorMessage = $this->validateRequiredFields($formData, $requiredFields);
            if ($errorMessage) {
                return $this->fail($errorMessage);
            }

            // 验证邮箱格式
            if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->fail('邮箱格式无效');
            }

            // 创建联系表单记录
            $formData['status'] = 'unprocessed'; // 默认状态为未处理
            $result = $this->contactFormRepository->create($formData);
            if (!$result) {
                return $this->fail('提交失败，请稍后重试');
            }

            // 发送邮件通知（可选）
            try {
                $this->mailService->sendContactFormNotification($result->toArray());
            } catch (Exception $e) {
                // 记录邮件发送失败，但不影响表单提交
                $this->logger->warning('联系表单通知邮件发送失败: ' . $e->getMessage(), ['form_id' => $result->id]);
            }

            return $this->success(
                [
                    'id' => $result->id,
                    'created_at' => $result->created_at,
                ],
                '感谢您的留言，我们会尽快回复您'
            );
        }, '创建联系表单异常', $formData);
    }

    /**
     * 获取联系表单列表.
     *
     * @param array<string, mixed> $conditions 查询条件
     * @param array<string, string> $order 排序方式
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array 操作结果
     */
    public function getContactForms(array $conditions = [], array $order = ['created_at' => 'desc'], int $page = 1, int $limit = 20): array
    {
        return $this->executeWithErrorHandling(function () use ($conditions, $order, $page, $limit) {
            $offset = ($page - 1) * $limit;
            $forms = $this->contactFormRepository->findAllBy($conditions, ['*'], $order, $limit, $offset);
            $total = $this->contactFormRepository->count($conditions);

            // 转换为数组格式
            $data = [];
            foreach ($forms as $form) {
                $data[] = [
                    'id' => $form->id,
                    'name' => $form->name,
                    'email' => $form->email,
                    'subject' => $form->subject,
                    'message' => $form->message,
                    'status' => $form->status,
                    'created_at' => $form->created_at,
                    'updated_at' => $form->updated_at,
                ];
            }

            return $this->success([
                'items' => $data,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ]);
        }, '获取联系表单列表异常', $conditions);
    }

    /**
     * 获取单个联系表单详情.
     *
     * @param int $id 表单ID
     * @return array 操作结果
     */
    public function getContactFormDetail(int $id): array
    {
        return $this->executeWithErrorHandling(function () use ($id) {
            $form = $this->contactFormRepository->findById($id);
            if (!$form) {
                return $this->fail('联系表单不存在');
            }

            $data = [
                'id' => $form->id,
                'name' => $form->name,
                'email' => $form->email,
                'subject' => $form->subject,
                'message' => $form->message,
                'phone' => $form->phone,
                'status' => $form->status,
                'created_at' => $form->created_at,
                'updated_at' => $form->updated_at,
            ];

            return $this->success($data);
        }, '获取联系表单详情异常', ['form_id' => $id]);
    }

    /**
     * 标记表单为已处理.
     *
     * @param int $id 表单ID
     * @param string $processorNote 处理备注
     * @return array 操作结果
     */
    public function markAsProcessed(int $id, string $processorNote = ''): array
    {
        return $this->executeWithErrorHandling(function () use ($id, $processorNote) {
            $form = $this->contactFormRepository->findById($id);
            if (!$form) {
                return $this->fail('联系表单不存在');
            }

            $result = $this->contactFormRepository->markAsProcessed($id, $processorNote);
            if ($result) {
                $this->logAction('标记表单为已处理', ['form_id' => $id]);
                return $this->success(null, '表单已标记为已处理');
            }

            return $this->fail('更新状态失败');
        }, '标记表单为已处理异常', ['form_id' => $id]);
    }
}
