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

use App\Repository\SubscriptionRepository;
use Carbon\Carbon;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\Str;
use Psr\Log\LoggerInterface;

/**
 * 订阅服务层
 * 处理订阅相关的业务逻辑.
 */
class SubscriptionService
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var SubscriptionRepository
     */
    protected $subscriptionRepository;

    /**
     * @Inject
     * @var MailService
     */
    protected $mailService;

    /**
     * 创建订阅.
     *
     * @param array<string, mixed> $subscriptionData 订阅数据
     * @return array 操作结果
     */
    public function createSubscription(array $subscriptionData): array
    {
        try {
            // 验证必要字段
            if (! isset($subscriptionData['email']) || empty($subscriptionData['email'])) {
                return [
                    'success' => false,
                    'message' => '邮箱不能为空',
                ];
            }

            // 验证邮箱格式
            if (! filter_var($subscriptionData['email'], FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => '邮箱格式无效',
                ];
            }

            // 检查邮箱是否已订阅
            $existing = $this->subscriptionRepository->findByEmail($subscriptionData['email']);
            if ($existing) {
                if ($existing->status === 'subscribed') {
                    return [
                        'success' => false,
                        'message' => '您已经订阅过了',
                    ];
                }
                if ($existing->status === 'pending') {
                    // 重新发送确认邮件
                    return $this->resendConfirmation($existing->id);
                }
            }

            // 生成确认token
            $token = Str::random(64);

            // 创建订阅记录
            $data = [
                'email' => $subscriptionData['email'],
                'name' => $subscriptionData['name'] ?? '',
                'subscription_type' => $subscriptionData['subscription_type'] ?? 'newsletter',
                'status' => 'pending',
                'confirm_token' => $token,
                'confirm_expire_at' => Carbon::now()->addDays(7)->toDateTimeString(),
            ];

            $result = $this->subscriptionRepository->create($data);
            if (! $result) {
                return [
                    'success' => false,
                    'message' => '订阅失败，请稍后重试',
                ];
            }

            // 发送确认邮件
            try {
                $confirmUrl = $this->generateConfirmUrl($token);
                $this->mailService->sendSubscriptionConfirmation($result->toArray(), $confirmUrl);
            } catch (Exception $e) {
                // 记录邮件发送失败，但不影响订阅创建
                $this->logger->warning('订阅确认邮件发送失败: ' . $e->getMessage(), ['subscription_id' => $result->id]);
            }

            return [
                'success' => true,
                'message' => '订阅成功，请查收邮件确认订阅',
                'data' => [
                    'id' => $result->id,
                    'email' => $result->email,
                ],
            ];
        } catch (Exception $e) {
            $this->logger->error('创建订阅异常: ' . $e->getMessage(), $subscriptionData);
            return [
                'success' => false,
                'message' => '系统异常，请稍后重试',
            ];
        }
    }

    /**
     * 确认订阅.
     *
     * @param string $token 确认token
     * @return array 操作结果
     */
    public function confirmSubscription(string $token): array
    {
        try {
            $subscription = $this->subscriptionRepository->findByToken($token);
            if (! $subscription) {
                return [
                    'success' => false,
                    'message' => '无效的确认链接',
                ];
            }

            // 检查是否已过期
            if ($subscription->confirm_expire_at && Carbon::parse($subscription->confirm_expire_at)->isPast()) {
                return [
                    'success' => false,
                    'message' => '确认链接已过期，请重新订阅',
                ];
            }

            // 更新订阅状态
            $result = $this->subscriptionRepository->update($subscription->id, [
                'status' => 'subscribed',
                'confirmed_at' => Carbon::now()->toDateTimeString(),
                'confirm_token' => null,
                'confirm_expire_at' => null,
            ]);

            if ($result) {
                return [
                    'success' => true,
                    'message' => '订阅确认成功！',
                ];
            }

            return [
                'success' => false,
                'message' => '确认失败，请稍后重试',
            ];
        } catch (Exception $e) {
            $this->logger->error('确认订阅异常: ' . $e->getMessage(), ['token' => $token]);
            return [
                'success' => false,
                'message' => '系统异常，请稍后重试',
            ];
        }
    }

    /**
     * 取消订阅.
     *
     * @param string $token 取消订阅token
     * @return array 操作结果
     */
    public function unsubscribe(string $token): array
    {
        try {
            $subscription = $this->subscriptionRepository->findByUnsubscribeToken($token);
            if (! $subscription) {
                return [
                    'success' => false,
                    'message' => '无效的取消订阅链接',
                ];
            }

            // 更新订阅状态
            $result = $this->subscriptionRepository->update($subscription->id, [
                'status' => 'unsubscribed',
                'unsubscribed_at' => Carbon::now()->toDateTimeString(),
            ]);

            if ($result) {
                return [
                    'success' => true,
                    'message' => '您已成功取消订阅',
                ];
            }

            return [
                'success' => false,
                'message' => '取消订阅失败，请稍后重试',
            ];
        } catch (Exception $e) {
            $this->logger->error('取消订阅异常: ' . $e->getMessage(), ['token' => $token]);
            return [
                'success' => false,
                'message' => '系统异常，请稍后重试',
            ];
        }
    }

    /**
     * 重新发送确认邮件.
     *
     * @param int $id 订阅ID
     * @return array 操作结果
     */
    public function resendConfirmation(int $id): array
    {
        try {
            $subscription = $this->subscriptionRepository->findById($id);
            if (! $subscription) {
                return [
                    'success' => false,
                    'message' => '订阅记录不存在',
                ];
            }

            // 生成新的确认token
            $token = Str::random(64);
            $this->subscriptionRepository->update($id, [
                'confirm_token' => $token,
                'confirm_expire_at' => Carbon::now()->addDays(7)->toDateTimeString(),
            ]);

            // 发送确认邮件
            $confirmUrl = $this->generateConfirmUrl($token);
            $this->mailService->sendSubscriptionConfirmation($subscription->toArray(), $confirmUrl);

            return [
                'success' => true,
                'message' => '确认邮件已重新发送，请查收',
            ];
        } catch (Exception $e) {
            $this->logger->error('重新发送确认邮件异常: ' . $e->getMessage(), ['subscription_id' => $id]);
            return [
                'success' => false,
                'message' => '邮件发送失败，请稍后重试',
            ];
        }
    }

    /**
     * 获取订阅列表（管理员功能）.
     *
     * @param array<string, mixed> $conditions 查询条件
     * @param array<string, string> $order 排序方式
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array 操作结果
     */
    public function getSubscriptions(array $conditions = [], array $order = ['created_at' => 'desc'], int $page = 1, int $limit = 20): array
    {
        try {
            $offset = ($page - 1) * $limit;
            $subscriptions = $this->subscriptionRepository->findAllBy($conditions, ['*'], $order, $limit, $offset);
            $total = $this->subscriptionRepository->count($conditions);

            // 转换为数组格式
            $data = [];
            foreach ($subscriptions as $subscription) {
                $data[] = [
                    'id' => $subscription->id,
                    'email' => $subscription->email,
                    'name' => $subscription->name,
                    'subscription_type' => $subscription->subscription_type,
                    'status' => $subscription->status,
                    'created_at' => $subscription->created_at,
                    'confirmed_at' => $subscription->confirmed_at,
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'items' => $data,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit),
                ],
            ];
        } catch (Exception $e) {
            $this->logger->error('获取订阅列表异常: ' . $e->getMessage(), $conditions);
            return [
                'success' => false,
                'message' => '获取列表失败',
            ];
        }
    }

    /**
     * 生成确认URL.
     *
     * @param string $token 确认token
     * @return string 确认URL
     */
    protected function generateConfirmUrl(string $token): string
    {
        // 这里应该从配置中获取域名，暂时硬编码
        return 'https://example.com/api/subscriptions/confirm/' . $token;
    }
}
