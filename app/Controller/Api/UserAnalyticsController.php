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
use App\Service\UserAnalyticsService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

/**
 * 用户分析控制器
 * 处理用户分析相关的HTTP请求.
 */
#[Controller]
#[RequestMapping(prefix: '/api/user-analytics')]
class UserAnalyticsController extends AbstractController
{
    /**
     * @Inject
     * @var UserAnalyticsService
     */
    protected $userAnalyticsService;

    /**
     * 记录用户事件
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'record-event', methods: ['POST'])]
    public function recordEvent(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $request->all();
        
        // 从请求中提取必要信息
        $eventData = [
            'user_id' => $data['user_id'] ?? null,
            'event_type' => $data['event_type'] ?? 'page_view',
            'event_data' => $data['event_data'] ?? [],
            'session_id' => $data['session_id'] ?? $request->global('request_id'),
            'ip_address' => $request->header('x-real-ip') ?? $request->getServerParams()['remote_addr'] ?? '',
            'user_agent' => $request->header('user-agent') ?? '',
            'page_url' => $data['page_url'] ?? '',
            'referrer' => $request->header('referer') ?? '',
        ];

        $result = $this->userAnalyticsService->recordUserEvent($eventData);
        
        if ($result['success']) {
            return $this->success($result['data'], $result['message']);
        }
        
        return $this->fail($result['message']);
    }

    /**
     * 获取用户活动记录
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'user-activity/{userId}', methods: ['GET'])]
    public function getUserActivity(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (int) $request->input('userId');
        $limit = (int) $request->input('limit', 50);

        // 权限验证：用户只能查看自己的活动记录
        $currentUser = $this->user ?? null;
        if (! $currentUser || $currentUser->id !== $userId) {
            return $this->fail('无权访问', 403);
        }

        $result = $this->userAnalyticsService->getUserActivity($userId, $limit);
        
        if ($result['success']) {
            return $this->success($result['data']);
        }
        
        return $this->fail($result['message']);
    }

    /**
     * 获取统计数据（管理员功能）
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'stats', methods: ['GET'])]
    public function getStats(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // 权限验证：仅管理员可访问
        $currentUser = $this->user ?? null;
        if (! $currentUser || ! $currentUser->is_admin) {
            return $this->fail('无权访问', 403);
        }

        $conditions = [];
        
        // 处理查询条件
        if ($startDate = $request->input('start_date')) {
            $conditions['created_at'] = ['$gte' => $startDate];
        }
        
        if ($endDate = $request->input('end_date')) {
            if (! isset($conditions['created_at'])) {
                $conditions['created_at'] = [];
            }
            $conditions['created_at']['$lte'] = $endDate;
        }
        
        if ($eventType = $request->input('event_type')) {
            $conditions['event_type'] = $eventType;
        }

        $result = $this->userAnalyticsService->getUserStats($conditions);
        
        if ($result['success']) {
            return $this->success($result['data']);
        }
        
        return $this->fail($result['message']);
    }

    /**
     * 清理过期数据（管理员功能）
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'clean-old-data', methods: ['POST'])]
    public function cleanOldData(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // 权限验证：仅管理员可访问
        $currentUser = $this->user ?? null;
        if (! $currentUser || ! $currentUser->is_admin) {
            return $this->fail('无权访问', 403);
        }

        $days = (int) $request->input('days', 90);
        
        $result = $this->userAnalyticsService->cleanOldAnalyticsData($days);
        
        if ($result['success']) {
            return $this->success($result['data'], $result['message']);
        }
        
        return $this->fail($result['message']);
    }
}