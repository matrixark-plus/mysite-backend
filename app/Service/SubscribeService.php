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

use App\Model\Subscribe;
use App\Repository\SubscribeRepository;
use Carbon\Carbon;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Redis\RedisFactory;
use Psr\Log\LoggerInterface;
use Redis;

class SubscribeService
{
    /**
     * @Inject
     * @var Redis
     */
    protected $redis;

    /**
     * @Inject
     * @var MailService
     */
    protected $mailService;

    /**
     * @Inject
     * @var SubscribeRepository
     */
    protected $subscribeRepository;

    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var RedisFactory
     */
    protected $redisFactory;

    /**
     * 构造函数
     */
    public function __construct()
    {
        // Redis实例在构造时初始化以保持兼容性
        $this->redis = $this->redisFactory->get('default');
    }

    /**
     * 添加博客订阅.
     *
     * @param string $email 邮箱地址
     * @return array 订阅结果
     */
    public function addBlogSubscribe($email)
    {
        try {
            // 检查是否已订阅
            $existing = $this->subscribeRepository->findByEmail($email);
            if ($existing) {
                if ($existing->status == Subscribe::STATUS_CONFIRMED) {
                    return [
                        'success' => false,
                        'message' => '您已订阅过博客更新',
                    ];
                }
                // 重新发送确认邮件
                return $this->resendConfirmation($email);
            }

            // 生成确认token
            $token = $this->generateToken();

            // 创建订阅记录
            $subscribeData = [
                'email' => $email,
                'type' => Subscribe::TYPE_BLOG,
                'token' => $token,
                'status' => Subscribe::STATUS_PENDING,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
            
            $subscribe = $this->subscribeRepository->create($subscribeData);
            
            if (! $subscribe) {
                return [
                    'success' => false,
                    'message' => '创建订阅记录失败，请稍后重试',
                ];
            }

            // 发送确认邮件
            $confirmUrl = $this->generateConfirmUrl($token);
            $result = $this->mailService->sendSubscribeConfirmation($email, $confirmUrl);

            if ($result) {
                return [
                    'success' => true,
                    'message' => '订阅成功，请查收验证邮件',
                ];
            }
            
            // 发送失败，删除记录
            $this->subscribeRepository->delete($subscribe->id);
            return [
                'success' => false,
                'message' => '邮件发送失败，请稍后重试',
            ];
        } catch (Exception $e) {
            $this->logger->error('添加订阅异常: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '订阅失败，请稍后重试',
            ];
        }
    }

    /**
     * 确认订阅.
     *
     * @param string $token 确认token
     * @return array 确认结果
     */
    public function confirmSubscribe($token)
    {
        try {
            // 根据token查找订阅记录
            $subscribe = $this->subscribeRepository->findByToken($token);

            if (! $subscribe) {
                return [
                    'success' => false,
                    'message' => '无效的订阅确认链接',
                ];
            }

            // 更新订阅状态
            $updateData = [
                'status' => Subscribe::STATUS_CONFIRMED,
                'confirmed_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
            
            $success = $this->subscribeRepository->update($subscribe->id, $updateData);
            
            if ($success) {
                return [
                    'success' => true,
                    'message' => '订阅确认成功！',
                ];
            }
            
            return [
                'success' => false,
                'message' => '更新订阅状态失败，请稍后重试',
            ];
        } catch (Exception $e) {
            $this->logger->error('确认订阅异常: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '订阅确认失败，请稍后重试',
            ];
        }
    }

    /**
     * 重新发送确认邮件.
     *
     * @param string $email 邮箱地址
     * @return array 发送结果
     */
    public function resendConfirmation($email)
    {
        try {
            $subscribe = $this->subscribeRepository->findByEmail($email);
            if (! $subscribe) {
                return [
                    'success' => false,
                    'message' => '未找到订阅记录',
                ];
            }

            // 生成新的token
            $token = $this->generateToken();
            $updateData = [
                'token' => $token,
                'updated_at' => Carbon::now(),
            ];
            
            $success = $this->subscribeRepository->update($subscribe->id, $updateData);
            
            if (! $success) {
                return [
                    'success' => false,
                    'message' => '更新token失败，请稍后重试',
                ];
            }

            // 发送确认邮件
            $confirmUrl = $this->generateConfirmUrl($token);
            $result = $this->mailService->sendSubscribeConfirmation($email, $confirmUrl);

            if ($result) {
                return [
                    'success' => true,
                    'message' => '确认邮件已重新发送，请查收',
                ];
            }
            return [
                'success' => false,
                'message' => '邮件发送失败，请稍后重试',
            ];
        } catch (Exception $e) {
            $this->logger->error('重新发送确认邮件异常: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '操作失败，请稍后重试',
            ];
        }
    }

    /**
     * 获取已确认的订阅者列表.
     *
     * @param string $type 订阅类型
     * @return array
     */
    public function getConfirmedSubscribers($type = Subscribe::TYPE_BLOG)
    {
        return $this->subscribeRepository->getConfirmedSubscribers($type);
    }


    /**
     * 生成订阅token.
     *
     * @return string
     */
    protected function generateToken()
    {
        return md5(uniqid(rand(), true));
    }

    /**
     * 生成确认链接.
     *
     * @param string $token
     * @return string
     */
    protected function generateConfirmUrl($token)
    {
        // 使用更符合当前API路径规范的URL
        $baseUrl = 'http://example.com';
        return "{$baseUrl}/api/subscribe/confirm?token={$token}";
    }
}
