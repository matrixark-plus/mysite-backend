<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\DbConnection\Db;
use Hyperf\Redis\RedisFactory;
use Hyperf\Config\ConfigInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerInterface;
use Carbon\Carbon;
// 注意：权限管理相关功能已移至PermissionService


class SystemService
{
    /**
     * @Inject
     * @var \Redis
     */
    protected $redis;
    
    /**
     * @Inject
     * @var ConfigInterface
     */
    protected $config;
    
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * 获取统计数据
     * 
     * @param array $params 查询参数
     * @return array 统计数据
     */
    public function getStatistics(array $params = []): array
    {
        try {
            // 构建缓存键
            $cacheKey = 'system:statistics:' . md5(json_encode($params));
            
            // 尝试从缓存获取（如果不在管理员面板且缓存存在）
            $isAdmin = isset($params['admin']) && $params['admin'];
            if (!$isAdmin) {
                $cached = $this->redis->get($cacheKey);
                if ($cached) {
                    return json_decode($cached, true);
                }
            }
            
            // 获取时间范围
            $timeRange = $this->getTimeRange($params);
            
            // 构建统计数据
            $statistics = [
                'user_count' => $this->getUserCount($timeRange),
                'article_count' => $this->getArticleCount($timeRange),
                'comment_count' => $this->getCommentCount($timeRange),
                'view_count' => $this->getViewCount($timeRange),
                'recent_activities' => $this->getRecentActivities($params),
                'daily_stats' => $this->getDailyStats($timeRange)
            ];
            
            // 设置缓存（非管理员面板）
            if (!$isAdmin) {
                $this->redis->set($cacheKey, json_encode($statistics), 300); // 5分钟缓存
            }
            
            return $statistics;
        } catch (\Exception $e) {
            $this->logger->error('获取统计数据异常: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 获取用户统计
     * 
     * @param array $timeRange 时间范围
     * @return int
     */
    protected function getUserCount(array $timeRange): int
    {
        $query = Db::table('users');
        
        if (isset($timeRange['start'])) {
            $query->where('created_at', '>=', $timeRange['start']);
        }
        if (isset($timeRange['end'])) {
            $query->where('created_at', '<=', $timeRange['end']);
        }
        
        return $query->count();
    }
    
    /**
     * 获取文章统计
     * 
     * @param array $timeRange 时间范围
     * @return int
     */
    protected function getArticleCount(array $timeRange): int
    {
        $query = Db::table('articles')
            ->where('status', 1); // 只统计已发布的
        
        if (isset($timeRange['start'])) {
            $query->where('created_at', '>=', $timeRange['start']);
        }
        if (isset($timeRange['end'])) {
            $query->where('created_at', '<=', $timeRange['end']);
        }
        
        return $query->count();
    }
    
    /**
     * 获取评论统计
     * 
     * @param array $timeRange 时间范围
     * @return int
     */
    protected function getCommentCount(array $timeRange): int
    {
        $query = Db::table('comments')
            ->where('status', 1); // 只统计已审核通过的
        
        if (isset($timeRange['start'])) {
            $query->where('created_at', '>=', $timeRange['start']);
        }
        if (isset($timeRange['end'])) {
            $query->where('created_at', '<=', $timeRange['end']);
        }
        
        return $query->count();
    }
    
    /**
     * 获取浏览量统计
     * 
     * @param array $timeRange 时间范围
     * @return int
     */
    protected function getViewCount(array $timeRange): int
    {
        // 这里假设浏览量存储在redis中
        // 实际项目中可能需要从日志或专门的统计表中获取
        $cacheKey = 'statistics:view_count';
        $totalViews = $this->redis->get($cacheKey);
        return $totalViews ? (int)$totalViews : 0;
    }
    
    /**
     * 获取最近活动
     * 
     * @param array $params 查询参数
     * @return array
     */
    protected function getRecentActivities(array $params): array
    {
        $limit = $params['limit'] ?? 10;
        
        // 从活动日志表获取最近活动并转换为数组
        return Db::table('activity_logs')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
    
    /**
     * 获取每日统计数据
     * 
     * @param array $timeRange 时间范围
     * @return array
     */
    protected function getDailyStats(array $timeRange): array
    {
        // 这里简化处理，实际项目中可能需要更复杂的统计逻辑
        $days = $this->calculateDays($timeRange);
        $stats = [];
        
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::parse($timeRange['start'])->addDays($i);
            $dateStr = $date->format('Y-m-d');
            
            // 这里应该从每日统计表中获取数据
            // 简化处理，返回空数据
            $stats[] = [
                'date' => $dateStr,
                'user_count' => 0,
                'article_count' => 0,
                'comment_count' => 0,
                'view_count' => 0
            ];
        }
        
        return $stats;
    }
    
    /**
     * 获取系统配置
     * 
     * @param string $key 配置键
     * @return array|mixed
     */
    public function getConfig(?string $key = null): mixed
    {
        try {
            // 尝试从缓存获取
            $cacheKey = 'system:config' . ($key ? ':' . $key : '');
            $cached = $this->redis->get($cacheKey);
            
            if ($cached) {
                return json_decode($cached, true);
            }
            
            // 从数据库获取配置
            $query = Db::table('system_configs');
            
            if ($key) {
                $config = $query->where('key', $key)->first();
                $result = $config ? json_decode($config->value, true) : null;
            } else {
                $configs = $query->get();
                $result = [];
                foreach ($configs as $config) {
                    $result[$config->key] = json_decode($config->value, true);
                }
            }
            
            // 设置缓存，1小时过期
            $this->redis->set($cacheKey, json_encode($result), 3600);
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('获取系统配置异常: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 更新系统配置
     * 
     * @param string $key 配置键
     * @param mixed $value 配置值
     * @return bool
     */
    public function updateConfig(string $key, mixed $value): bool
    {
        try {
            // 验证配置键是否合法
            if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $key)) {
                throw new \Exception('配置键格式不合法');
            }
            
            // 序列化配置值
            $valueStr = json_encode($value);
            
            // 更新数据库
            $result = Db::table('system_configs')
                ->updateOrInsert(
                    ['key' => $key],
                    ['value' => $valueStr, 'updated_at' => Carbon::now()->toDateTimeString()]
                );
            
            // 清除缓存
            $this->redis->del('system:config');
            $this->redis->del('system:config:' . $key);
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('更新系统配置异常: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 批量更新系统配置
     * 
     * @param array $configs 配置数组
     * @return bool
     */
    public function batchUpdateConfig(array $configs): bool
    {
        try {
            Db::beginTransaction();
            
            foreach ($configs as $key => $value) {
                $this->updateConfig($key, $value);
            }
            
            Db::commit();
            
            return true;
        } catch (\Exception $e) {
            Db::rollBack();
            $this->logger->error('批量更新系统配置异常: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 获取时间范围
     * 
     * @param array $params 查询参数
     * @return array
     */
    protected function getTimeRange(array $params): array
    {
        $timeRange = [];
        
        if (isset($params['date_range'])) {
            $dateRange = $params['date_range'];
            if (isset($dateRange['start']) && $dateRange['start']) {
                $timeRange['start'] = $dateRange['start'];
            }
            if (isset($dateRange['end']) && $dateRange['end']) {
                $timeRange['end'] = $dateRange['end'];
            }
        }
        
        // 默认时间范围为最近30天
        if (empty($timeRange)) {
            $timeRange['start'] = Carbon::now()->subDays(30)->toDateTimeString();
            $timeRange['end'] = Carbon::now()->toDateTimeString();
        }
        
        return $timeRange;
    }
    
    /**
     * 计算天数
     * 
     * @param array $timeRange 时间范围
     * @return int
     */
    protected function calculateDays(array $timeRange): int
    {
        if (!isset($timeRange['start']) || !isset($timeRange['end'])) {
            return 30; // 默认30天
        }
        
        $start = Carbon::parse($timeRange['start']);
        $end = Carbon::parse($timeRange['end']);
        
        return $start->diffInDays($end) + 1;
    }
    
    // 权限管理相关功能已移至PermissionService
}