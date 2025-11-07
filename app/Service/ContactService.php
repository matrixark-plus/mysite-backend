<?php

namespace App\Service;

use Hyperf\DbConnection\Db;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Redis\RedisFactory;
use Carbon\Carbon;

class ContactService
{
    /**
     * @var \Redis
     */
    protected $redis;
    
    /**
     * @var MailService
     */
    protected $mailService;
    
    public function __construct()
    {
        $container = ApplicationContext::getContainer();
        $this->redis = $container->get(RedisFactory::class)->get('default');
        $this->mailService = $container->get(MailService::class);
    }
    
    /**
     * 提交联系表单
     * 
     * @param array $data 表单数据
     * @return array 提交结果
     */
    public function submitContactForm($data)
    {
        try {
            // 验证数据
            $validation = $this->validateContactData($data);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }
            
            // 保存联系记录
            $contactId = $this->saveContactRecord($data);
            
            // 发送通知邮件给管理员
        $adminEmail = env('CONTACT_EMAIL', 'admin@example.com');
        $result = $this->mailService->sendCommentNotification($adminEmail, $data);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => '感谢您的留言，我们会尽快回复您！'
                ];
            } else {
                // 邮件发送失败，记录日志但仍然返回成功，不影响用户体验
                logger()->warning('联系表单邮件通知发送失败，联系ID: ' . $contactId);
                return [
                    'success' => true,
                    'message' => '感谢您的留言，我们会尽快回复您！'
                ];
            }
        } catch (\Exception $e) {
            logger()->error('提交联系表单异常: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '提交失败，请稍后重试'
            ];
        }
    }
    
    /**
     * 验证联系表单数据
     * 
     * @param array $data
     * @return array
     */
    protected function validateContactData($data)
    {
        // 检查必填字段
        $requiredFields = ['name', 'email', 'subject', 'message'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return [
                    'valid' => false,
                    'message' => '请填写所有必填字段'
                ];
            }
        }
        
        // 验证邮箱格式
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => '邮箱格式不正确'
            ];
        }
        
        // 验证消息长度
        if (strlen($data['message']) > 1000) {
            return [
                'valid' => false,
                'message' => '留言内容不能超过1000个字符'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * 保存联系记录
     * 
     * @param array $data
     * @return int
     */
    protected function saveContactRecord($data)
    {
        return Db::table('contacts')->insertGetId([
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'phone' => $data['phone'] ?? '',
            'ip' => $data['ip'] ?? '',
            'status' => 0, // 0: 未处理, 1: 已处理
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);
    }
    
    /**
     * 获取联系记录列表
     * 
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param array $filters 筛选条件
     * @return array
     */
    public function getContactList($page = 1, $pageSize = 20, $filters = [])
    {
        $query = Db::table('contacts');
        
        // 应用筛选条件
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }
        
        if (isset($filters['date_range'])) {
            $dateRange = $filters['date_range'];
            if (isset($dateRange['start']) && $dateRange['start']) {
                $query->where('created_at', '>=', $dateRange['start']);
            }
            if (isset($dateRange['end']) && $dateRange['end']) {
                $query->where('created_at', '<=', $dateRange['end']);
            }
        }
        
        // 获取总数
        $total = $query->count();
        
        // 获取列表
        $list = $query
            ->orderBy('created_at', 'desc')
            ->forPage($page, $pageSize)
            ->get();
        
        return [
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'list' => $list
        ];
    }
    
    /**
     * 标记联系记录为已处理
     * 
     * @param int $id 联系记录ID
     * @return bool
     */
    public function markAsProcessed($id)
    {
        return Db::table('contacts')
            ->where('id', $id)
            ->update([
                'status' => 1,
                'processed_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString()
            ]) > 0;
    }
}