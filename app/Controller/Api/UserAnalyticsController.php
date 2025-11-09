<?php

declare(strict_types=1);
/**
 * 用户分析控制器
 * 处理用户分析相关的HTTP请求.
 */

namespace App\Controller\Api;

use App\Constants\StatusCode;
use App\Controller\AbstractController;
use App\Service\UserAnalyticsService;
use App\Traits\LogTrait;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;

/**
 * 用户分析控制器
 * 处理用户分析相关的HTTP请求.
 * @Controller(prefix="/api/user-analytics")
 */
class UserAnalyticsController extends AbstractController
{
    use LogTrait;
    
    /**
     * @Inject
     * @var UserAnalyticsService
     */
    protected $userAnalyticsService;

    /**
     * 记录用户事件
     * @RequestMapping(path="record-event", methods={"POST"})
     */
    public function recordEvent()
    {
        try {
            $data = $this->request->all();
            
            // 从请求中提取必要信息
            $eventData = [
                'user_id' => $data['user_id'] ?? null,
                'event_type' => $data['event_type'] ?? 'page_view',
                'event_data' => $data['event_data'] ?? [],
                'session_id' => $data['session_id'] ?? $this->request->getAttribute('request_id'),
                'ip_address' => $this->request->header('x-real-ip') ?? $this->request->getServerParams()['remote_addr'] ?? '',
                'user_agent' => $this->request->header('user-agent') ?? '',
                'page_url' => $data['page_url'] ?? '',
                'referrer' => $this->request->header('referer') ?? '',
            ];

            $result = $this->userAnalyticsService->recordUserEvent($eventData);
            
            if ($result['success']) {
                return $this->success($result['data'], $result['message']);
            }
            
            return $this->fail(StatusCode::BAD_REQUEST, $result['message']);
        } catch (\Exception $e) {
            $this->logError('记录用户事件失败', ['error' => $e->getMessage()], $e, 'analytics');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '记录用户事件失败');
        }
    }

    /**
     * 获取用户活动记录
     * @RequestMapping(path="user-activity/{userId}", methods={"GET"})
     */
    public function getUserActivity()
    {
        try {
            $userId = (int) $this->request->route('userId');
            $limit = (int) $this->request->input('limit', 50);

            // 权限验证：用户只能查看自己的活动记录
            $currentUser = $this->user ?? null;
            if (! $currentUser || $currentUser->id !== $userId) {
                return $this->fail(StatusCode::FORBIDDEN, '无权访问');
            }

            $result = $this->userAnalyticsService->getUserActivity($userId, $limit);
            
            if ($result['success']) {
                return $this->success($result['data']);
            }
            
            return $this->fail(StatusCode::BAD_REQUEST, $result['message']);
        } catch (\Exception $e) {
            $this->logError('获取用户活动记录失败', ['error' => $e->getMessage()], $e, 'analytics');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取用户活动记录失败');
        }
    }

    /**
     * 获取统计数据（管理员功能）
     * @RequestMapping(path="stats", methods={"GET"})
     */
    public function getStats()
    {
        try {
            // 权限验证：仅管理员可访问
            $currentUser = $this->user ?? null;
            if (! $currentUser || ! $currentUser->is_admin) {
                return $this->fail(StatusCode::FORBIDDEN, '无权访问');
            }

            $conditions = [];
            
            // 处理查询条件
            if ($startDate = $this->request->input('start_date')) {
                $conditions['created_at'] = ['$gte' => $startDate];
            }
            
            if ($endDate = $this->request->input('end_date')) {
                if (! isset($conditions['created_at'])) {
                    $conditions['created_at'] = [];
                }
                $conditions['created_at']['$lte'] = $endDate;
            }
            
            if ($eventType = $this->request->input('event_type')) {
                $conditions['event_type'] = $eventType;
            }

            $result = $this->userAnalyticsService->getUserStats($conditions);
            
            if ($result['success']) {
                return $this->success($result['data']);
            }
            
            return $this->fail(StatusCode::BAD_REQUEST, $result['message']);
        } catch (\Exception $e) {
            $this->logError('获取统计数据失败', ['error' => $e->getMessage()], $e, 'analytics');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取统计数据失败');
        }
    }

    /**
     * 清理过期数据（管理员功能）
     * @RequestMapping(path="clean-old-data", methods={"POST"})
     */
    public function cleanOldData()
    {
        try {
            // 权限验证：仅管理员可访问
            $currentUser = $this->user ?? null;
            if (! $currentUser || ! $currentUser->is_admin) {
                return $this->fail(StatusCode::FORBIDDEN, '无权访问');
            }

            $days = (int) $this->request->input('days', 90);
            
            $result = $this->userAnalyticsService->cleanOldAnalyticsData($days);
            
            if ($result['success']) {
                return $this->success($result['data'], $result['message']);
            }
            
            return $this->fail(StatusCode::BAD_REQUEST, $result['message']);
        } catch (\Exception $e) {
            $this->logError('清理过期数据失败', ['error' => $e->getMessage()], $e, 'analytics');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '清理过期数据失败');
        }
    }
}