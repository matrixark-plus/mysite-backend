<?php

namespace App\Service;

use Hyperf\DbConnection\Db;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Redis\RedisFactory;
use Carbon\Carbon;

class SubscribeService
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
     * 添加博客订阅
     * 
     * @param string $email 邮箱地址
     * @return array 订阅结果
     */
    public function addBlogSubscribe($email)
    {
        try {
            // 检查是否已订阅
            $existing = $this->getSubscribeByEmail($email);
            if ($existing) {
                if ($existing->status == 1) {
                    return [
                        'success' => false,
                        'message' => '您已订阅过博客更新'
                    ];
                } else {
                    // 重新发送确认邮件
                    return $this->resendConfirmation($email);
                }
            }
            
            // 生成确认token
            $token = $this->generateToken();
            $expireTime = Carbon::now()->addDay()->toDateTimeString();
            
            // 插入订阅记录
            $id = Db::table('subscribes')->insertGetId([
                'email' => $email,
                'type' => 'blog',
                'token' => $token,
                'status' => 0, // 0: 待确认, 1: 已确认
                'created_at' => Carbon::now()->toDateTimeString(),
                'updated_at' => Carbon::now()->toDateTimeString(),
            ]);
            
            // 发送确认邮件
            $confirmUrl = $this->generateConfirmUrl($token);
            $result = $this->mailService->sendSubscribeConfirmation($email, $confirmUrl);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => '订阅成功，请查收验证邮件'
                ];
            } else {
                // 发送失败，删除记录
                Db::table('subscribes')->where('id', $id)->delete();
                return [
                    'success' => false,
                    'message' => '邮件发送失败，请稍后重试'
                ];
            }
        } catch (\Exception $e) {
            logger()->error('添加订阅异常: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '订阅失败，请稍后重试'
            ];
        }
    }
    
    /**
     * 确认订阅
     * 
     * @param string $token 确认token
     * @return array 确认结果
     */
    public function confirmSubscribe($token)
    {
        try {
            $subscribe = Db::table('subscribes')
                ->where('token', $token)
                ->where('status', 0)
                ->first();
            
            if (!$subscribe) {
                return [
                    'success' => false,
                    'message' => '无效的订阅确认链接'
                ];
            }
            
            // 更新订阅状态
            Db::table('subscribes')
                ->where('id', $subscribe->id)
                ->update([
                    'status' => 1,
                    'confirmed_at' => Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString(),
                ]);
            
            return [
                'success' => true,
                'message' => '订阅确认成功！'
            ];
        } catch (\Exception $e) {
            logger()->error('确认订阅异常: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '订阅确认失败，请稍后重试'
            ];
        }
    }
    
    /**
     * 重新发送确认邮件
     * 
     * @param string $email 邮箱地址
     * @return array 发送结果
     */
    public function resendConfirmation($email)
    {
        try {
            $subscribe = $this->getSubscribeByEmail($email);
            if (!$subscribe) {
                return [
                    'success' => false,
                    'message' => '未找到订阅记录'
                ];
            }
            
            // 生成新的token
            $token = $this->generateToken();
            Db::table('subscribes')
                ->where('id', $subscribe->id)
                ->update(['token' => $token]);
            
            // 发送确认邮件
            $confirmUrl = $this->generateConfirmUrl($token);
            $result = $this->mailService->sendSubscribeConfirmation($email, $confirmUrl);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => '确认邮件已重新发送，请查收'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => '邮件发送失败，请稍后重试'
                ];
            }
        } catch (\Exception $e) {
            logger()->error('重新发送确认邮件异常: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '操作失败，请稍后重试'
            ];
        }
    }
    
    /**
     * 获取已确认的订阅者列表
     * 
     * @param string $type 订阅类型
     * @return array
     */
    public function getConfirmedSubscribers($type = 'blog')
    {
        return Db::table('subscribes')
            ->where('type', $type)
            ->where('status', 1)
            ->pluck('email')
            ->toArray();
    }
    
    /**
     * 根据邮箱获取订阅记录
     * 
     * @param string $email 邮箱地址
     * @return mixed
     */
    protected function getSubscribeByEmail($email)
    {
        return Db::table('subscribes')
            ->where('email', $email)
            ->where('type', 'blog')
            ->first();
    }
    
    /**
     * 生成订阅token
     * 
     * @return string
     */
    protected function generateToken()
    {
        return md5(uniqid(rand(), true));
    }
    
    /**
     * 生成确认链接
     * 
     * @param string $token
     * @return string
     */
    protected function generateConfirmUrl($token)
    {
        // 这里应该使用配置的域名
        $baseUrl = 'http://example.com';
        return "{$baseUrl}/api/v1/subscribe/confirm?token={$token}";
    }
}