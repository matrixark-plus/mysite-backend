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

use App\Repository\UserAnalyticsRepository;
use Carbon\Carbon;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;
use Carbon\Carbon;

/**
 * 用户分析服务层
 * 处理用户分析相关的业务逻辑.
 */
class UserAnalyticsService
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var UserAnalyticsRepository
     */
    protected $userAnalyticsRepository;

    /**
     * 记录用户事件
     *
     * @param array<string, mixed> $eventData 事件数据
     * @return array 操作结果
     */
    public function recordUserEvent(array $eventData): array
    {
        try {
            // 确保必要字段存在
            $requiredFields = ['user_id', 'event_type', 'session_id', 'ip_address'];
            foreach ($requiredFields as $field) {
                if (! isset($eventData[$field])) {
                    return [
                        'success' => false,
                        'message' => "缺少必要字段: {$field}",
                    ];
                }
            }

            // 添加时间戳
            $eventData['created_at'] = Carbon::now()->toDateTimeString();

            // 记录事件
            $result = $this->userAnalyticsRepository->create($eventData);
            if ($result) {
                return [
                    'success' => true,
                    'message' => '事件记录成功',
                    'data' => [
                        'id' => $result['id'] ?? null,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => '事件记录失败',
            ];
        } catch (Exception $e) {
            $this->logger->error('记录用户事件异常: ' . $e->getMessage(), $eventData);
            return [
                'success' => false,
                'message' => '系统异常，请稍后重试',
            ];
        }
    }

    /**
     * 获取用户的活动记录
     *
     * @param int $userId 用户ID
     * @param int $limit 限制数量
     * @return array 操作结果
     */
    public function getUserActivity(int $userId, int $limit = 50): array
    {
        try {
            $activities = $this->userAnalyticsRepository->findByUserId($userId, ['created_at' => 'desc']);
            
            // 转换为数组格式
            $data = [];
            foreach ($activities as $activity) {
                $data[] = [
                    'id' => $activity->id,
                    'event_type' => $activity->event_type,
                    'event_data' => $activity->event_data,
                    'session_id' => $activity->session_id,
                    'created_at' => $activity->created_at,
                ];
            }

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (Exception $e) {
            $this->logger->error('获取用户活动记录异常: ' . $e->getMessage(), ['user_id' => $userId]);
            return [
                'success' => false,
                'message' => '获取用户活动记录失败',
            ];
        }
    }

    /**
     * 获取用户统计数据
     *
     * @param array<string, mixed> $conditions 查询条件
     * @return array 操作结果
     */
    public function getUserStats(array $conditions = []): array
    {
        try {
            // 获取总访问次数
            $totalVisits = $this->userAnalyticsRepository->count($conditions);

            // 获取独立用户数
            $conditions['event_type'] = 'page_view';
            $uniqueUsers = $this->userAnalyticsRepository->findAllBy($conditions, ['DISTINCT user_id']);
            $uniqueUserCount = $uniqueUsers->count();

            // 获取最近活动时间
            $latestActivity = $this->userAnalyticsRepository->findAllBy($conditions, ['created_at'], ['created_at' => 'desc']);
            $latestActivityTime = $latestActivity->isNotEmpty() ? $latestActivity->first()->created_at : null;

            return [
                'success' => true,
                'data' => [
                    'total_visits' => $totalVisits,
                    'unique_users' => $uniqueUserCount,
                    'latest_activity' => $latestActivityTime,
                ],
            ];
        } catch (Exception $e) {
            $this->logger->error('获取用户统计数据异常: ' . $e->getMessage(), $conditions);
            return [
                'success' => false,
                'message' => '获取统计数据失败',
            ];
        }
    }

    /**
     * 清理过期的分析数据
     *
     * @param int $days 保留天数
     * @return array 操作结果
     */
    public function cleanOldAnalyticsData(int $days = 90): array
    {
        try {
            $beforeDate = Carbon::now()->subDays($days)-u003etoDateTimeString();
            $deletedCount = $this->userAnalyticsRepository->cleanOldData($beforeDate);

            return [
                'success' => true,
                'message' => "成功清理 {$deletedCount} 条过期数据",
                'data' => [
                    'deleted_count' => $deletedCount,
                    'before_date' => $beforeDate,
                ],
            ];
        } catch (Exception $e) {
            $this->logger->error('清理过期分析数据异常: ' . $e->getMessage(), ['days' => $days]);
            return [
                'success' => false,
                'message' => '清理数据失败',
            ];
        }
    }
}