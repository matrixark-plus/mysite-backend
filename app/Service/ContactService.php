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

use App\Repository\ContactRepository;
use Carbon\Carbon;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Redis\RedisFactory;
use Psr\Log\LoggerInterface;
use Redis;

class ContactService
{
    /**
     * @Inject
     * @var Redis
     */
    protected $redis;

    /**
     * @Inject
     * @var RedisFactory
     */
    protected $redisFactory;

    /**
     * @Inject
     * @var MailService
     */
    protected $mailService;

    /**
     * @Inject
     * @var ContactRepository
     */
    protected $contactRepository;

    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct()
    {
        $this->redis = $this->redisFactory->get('default');
    }

    /**
     * 提交联系表单.
     *
     * @param array $data 表单数据
     * @return array 提交结果
     */
    public function submitContactForm($data)
    {
        try {
            // 验证数据
            $validation = $this->validateContactData($data);
            if (! $validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message'],
                ];
            }

            // 准备保存数据
            $contactData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'subject' => $data['subject'],
                'message' => $data['message'],
                'phone' => $data['phone'] ?? '',
                'ip' => $data['ip'] ?? '',
                'status' => 0, // 未处理状态
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];

            // 保存联系记录
            $contact = $this->contactRepository->create($contactData);
            
            if (! $contact) {
                return [
                    'success' => false,
                    'message' => '保存联系记录失败，请稍后重试',
                ];
            }

            // 发送通知邮件给管理员
            $adminEmail = env('CONTACT_EMAIL', 'admin@example.com');
            $result = $this->mailService->sendCommentNotification($adminEmail, $data);

            if ($result) {
                return [
                    'success' => true,
                    'message' => '感谢您的留言，我们会尽快回复您！',
                ];
            }
            // 邮件发送失败，记录日志但仍然返回成功，不影响用户体验
            $this->logger->warning('联系表单邮件通知发送失败，联系ID: ' . $contact->id);
            return [
                'success' => true,
                'message' => '感谢您的留言，我们会尽快回复您！',
            ];
        } catch (Exception $e) {
            $this->logger->error('提交联系表单异常: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '提交失败，请稍后重试',
            ];
        }
    }

    /**
     * 获取联系记录列表.
     *
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param array $filters 筛选条件
     * @return array
     */
    public function getContactList($page = 1, $pageSize = 20, $filters = [])
    {
        return $this->contactRepository->getContactList($page, $pageSize, $filters);
    }

    /**
     * 标记联系记录为已处理.
     *
     * @param int $id 联系记录ID
     * @return bool
     */
    public function markAsProcessed(int $id)
    {
        return $this->contactRepository->markAsProcessed($id);
    }

    /**
     * 验证联系表单数据.
     *
     * @param array $data
     * @return array
     */
    protected function validateContactData(array $data)
    {
        // 检查必填字段
        $requiredFields = ['name', 'email', 'subject', 'message'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return [
                    'valid' => false,
                    'message' => '请填写所有必填字段',
                ];
            }
        }

        // 验证邮箱格式
        if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => '邮箱格式不正确',
            ];
        }

        // 验证消息长度
        if (strlen($data['message']) > 1000) {
            return [
                'valid' => false,
                'message' => '留言内容不能超过1000个字符',
            ];
        }

        return ['valid' => true];
    }

    /**
     * 获取未处理的联系记录数量.
     *
     * @return int
     */
    public function getUnprocessedCount()
    {
        return $this->contactRepository->getUnprocessedCount();
    }

    /**
     * 删除联系表单.
     *
     * @param int $id 联系记录ID
     * @return array 删除结果
     */
    public function deleteContactForm(int $id)
    {
        try {
            // 先检查记录是否存在
            $contact = $this->contactRepository->findById($id);
            if (! $contact) {
                return [
                    'success' => false,
                    'message' => '联系记录不存在'
                ];
            }

            // 执行删除操作
            $success = $this->contactRepository->delete($id);
            
            if ($success) {
                return [
                    'success' => true,
                    'message' => '联系记录删除成功'
                ];
            }
            
            return [
                'success' => false,
                'message' => '联系记录删除失败'
            ];
        } catch (Exception $e) {
            $this->logger->error('删除联系表单异常: ' . $e->getMessage(), ['id' => $id]);
            return [
                'success' => false,
                'message' => '删除失败，请稍后重试'
            ];
        }
    }
}
