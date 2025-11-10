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

namespace App\Repository;

use Exception;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 活动日志Repository
 * 负责活动日志相关的数据库操作.
 */
class ActivityLogRepository extends BaseRepository
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 表名.
     *
     * @var string
     */
    protected $table = 'activity_logs';

    /**
     * 获取最近活动.
     *
     * @param int $limit 限制数量
     * @param array $conditions 额外条件
     * @return array 活动日志列表
     */
    public function getRecentActivities(int $limit = 10, array $conditions = []): array
    {
        try {
            $query = Db::table($this->table);

            // 添加额外条件
            foreach ($conditions as $key => $value) {
                if (is_array($value)) {
                    $query->whereIn($key, $value);
                } else {
                    $query->where($key, $value);
                }
            }

            $activities = $query
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            // 确保返回数组格式
            $result = [];
            foreach ($activities as $activity) {
                $result[] = (array) $activity;
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('获取最近活动失败: ' . $e->getMessage(), ['limit' => $limit, 'conditions' => $conditions]);
            return [];
        }
    }

    /**
     * 获取活动统计
     *
     * @param array $timeRange 时间范围
     * @return int 活动数量
     */
    public function count(array $timeRange = []): int
    {
        try {
            $query = Db::table($this->table);

            if (isset($timeRange['start'])) {
                $query->where('created_at', '>=', $timeRange['start']);
            }
            if (isset($timeRange['end'])) {
                $query->where('created_at', '<=', $timeRange['end']);
            }

            return $query->count();
        } catch (Exception $e) {
            $this->logger->error('获取活动统计失败: ' . $e->getMessage(), ['timeRange' => $timeRange]);
            return 0;
        }
    }

    /**
     * 获取模型类名.
     * @return string 模型类名
     */
    protected function getModel(): string
    {
        return 'App\Model\ActivityLog';
    }
}
