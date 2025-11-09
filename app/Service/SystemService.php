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

use Carbon\Carbon;
use Exception;
use Hyperf\Config\ConfigInterface;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;
use Hyperf\Redis\RedisFactory;
use Redis;
use App\Repository\UserRepository;
use App\Repository\BlogRepository;
use App\Repository\CommentRepository;
use App\Repository\ActivityLogRepository;

// 注意：权限管理相关功能已移至PermissionService
// 系统配置现在通过环境变量文件管理，不再使用数据库

class SystemService
{
    /**
     * @var RedisFactory
     */
    protected $redisFactory;

    /**
     * Redis实例.
     * @var mixed
     */
    protected $redis;

    /**
     * @var mixed
     */
    protected $config;

    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * @Inject
     * @var EnvironmentFileService
     */
    protected $environmentFileService;

    /**
     * @Inject
     * @var UserRepository
     */
    protected $userRepository;
    
    /**
     * @Inject
     * @var BlogRepository
     */
    protected $blogRepository;
    
    /**
     * @Inject
     * @var CommentRepository
     */
    protected $commentRepository;
    
    /**
     * @Inject
     * @var ActivityLogRepository
     */
    protected $activityLogRepository;
    
    /**
     * 构造函数. 注入依赖
     *
     * @param RedisFactory $redisFactory Redis工厂
     * @param mixed $config 配置接口
     * @param LoggerInterface $logger 日志接口
     */
    public function __construct(RedisFactory $redisFactory, $config, LoggerInterface $logger)
    {
        $this->redisFactory = $redisFactory;
        $this->config = $config;
        $this->logger = $logger;
        
        // 初始化Redis实例
        $this->redis = $this->redisFactory->get('default');
    }

    // 构造函数已移至上方，使用依赖注入的方式

    /**
     * 获取统计数据.
     *
     * @param array<string, mixed> $params 查询参数
     * @return array<string, mixed> 统计数据
     */
    public function getStatistics(array $params = []): array
    {
        try {
            // 构建缓存键，确保json_encode返回的是字符串
            $paramsJson = json_encode($params) ?: '{}';
            $cacheKey = 'system:statistics:' . md5($paramsJson);

            // 尝试从缓存获取（如果不在管理员面板且缓存存在）
            $isAdmin = isset($params['admin']) && $params['admin'];
            if (! $isAdmin) {
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
                'article_count' => $this->blogRepository->count($timeRange),
                'comment_count' => $this->commentRepository->count($timeRange),
                'view_count' => $this->getViewCount($timeRange),
                'recent_activities' => $this->getRecentActivities($params),
                'daily_stats' => $this->getDailyStats($timeRange),
            ];

            // 设置缓存（非管理员面板）
            if (! $isAdmin) {
                $this->redis->set($cacheKey, json_encode($statistics), 300); // 5分钟缓存
            }

            return $statistics;
        } catch (Exception $e) {
            $this->logger->error('获取统计数据异常: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取系统配置.
     *
     * @return array<string, string> 系统配置
     */
    public function getSystemConfig(): array
    {
        try {
            // 使用Hyperf ConfigInterface获取所有配置
            $configs = $this->getSystemConfigFromCache();

            // 转换为前端需要的格式
            return [
                'site_name' => $configs['site.name'] ?? '默认站点',
                'site_description' => $configs['site.description'] ?? '站点描述',
                'site_keywords' => $configs['site.keywords'] ?? '',
                'copyright' => $configs['site.copyright'] ?? '',
                'icp_info' => $configs['site.icp_info'] ?? '',
            ];
        } catch (Exception $e) {
            $this->logger->error('获取系统配置异常: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取系统配置（从环境变量文件和缓存）.
     *
     * @param string $key 配置键
     * @return array<string, mixed>|mixed
     */
    public function getSystemConfigFromCache(?string $key = null): mixed
    {
        try {
            // 尝试从缓存获取
            $cacheKey = 'system:config' . ($key ? ':' . $key : '');
            $cached = $this->redis->get($cacheKey);

            if ($cached) {
                return json_decode($cached, true);
            }

            // 从环境变量文件获取配置
            if ($key) {
                $result = $this->environmentFileService->getEnvVar($key);
            } else {
                $result = $this->environmentFileService->readEnvFile();
            }

            // 设置缓存，1小时过期
            $this->redis->set($cacheKey, json_encode($result), 3600);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('获取系统配置异常: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 允许修改的配置键白名单
     * 仅允许修改应用相关的非敏感配置
     */
    private const ALLOWED_CONFIG_KEYS = [
        'APP_NAME',
        'APP_DEBUG',
        'APP_TIMEZONE',
        'CACHE_DRIVER',
        'LOG_CHANNEL',
        'LOG_LEVEL',
        'DB_CONNECTION',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'REDIS_HOST',
        'REDIS_PORT',
        'REDIS_PASSWORD',
    ];

    /**
     * 更新系统配置.
     *
     * @param string $key 配置键
     * @param mixed $value 配置值
     * @return bool
     */
    public function updateConfig(string $key, mixed $value): bool
    {
        try {
            // 验证配置键是否合法
            if (! preg_match('/^[a-zA-Z0-9_\.]+$/', $key)) {
                throw new Exception('配置键格式不合法');
            }

            // 验证配置键是否在白名单中
            if (!in_array($key, self::ALLOWED_CONFIG_KEYS)) {
                $this->logger->warning('尝试修改非授权的配置项: ' . $key);
                throw new Exception('无权修改此配置项');
            }

            // 安全验证配置值
            $this->validateConfigValue($key, $value);

            // 通过环境变量文件服务更新配置
            $result = $this->environmentFileService->updateEnvVar($key, $value);

            // 清除缓存
            $this->redis->del('system:config');
            $this->redis->del('system:config:' . $key);

            // 记录配置修改日志
            $this->logger->info('系统配置已更新', ['key' => $key]);
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error('更新系统配置异常: ' . $e->getMessage(), ['key' => $key]);
            throw $e;
        }
    }

    /**
     * 验证配置值的安全性
     * @param string $key 配置键
     * @param mixed $value 配置值
     * @throws Exception
     */
    private function validateConfigValue(string $key, mixed $value): void
    {
        $valueStr = (string) $value;
        
        // 防止路径遍历攻击
        if (strpos($valueStr, '../') !== false || strpos($valueStr, '..\\') !== false) {
            throw new Exception('配置值包含非法字符');
        }
        
        // 验证数据库配置
        if (strpos($key, 'DB_') === 0 && $key !== 'DB_CONNECTION') {
            // 数据库连接字符串不能包含危险字符
            if (preg_match('/[;&|`\\$]/', $valueStr)) {
                throw new Exception('数据库配置包含非法字符');
            }
        }
        
        // 限制配置值长度，防止过大的输入
        if (mb_strlen($valueStr) > 500) {
            throw new Exception('配置值长度不能超过500字符');
        }
    }

    /**
     * 批量更新系统配置.
     *
     * @param array<string, mixed> $configs 配置数组
     * @return bool
     */
    public function batchUpdateConfig(array $configs): bool
    {
        try {
            // 验证批量更新数量限制
            if (count($configs) > 20) {
                throw new Exception('批量更新数量不能超过20个');
            }
            
            // 过滤和验证所有配置项
            $validatedConfigs = [];
            foreach ($configs as $key => $value) {
                // 验证配置键是否合法
                if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $key)) {
                    throw new Exception('配置键格式不合法: ' . $key);
                }
                
                // 验证配置键是否在白名单中
                if (!in_array($key, self::ALLOWED_CONFIG_KEYS)) {
                    $this->logger->warning('尝试修改非授权的配置项: ' . $key);
                    throw new Exception('无权修改此配置项: ' . $key);
                }
                
                // 安全验证配置值
                $this->validateConfigValue($key, $value);
                
                $validatedConfigs[$key] = $value;
            }
            
            // 通过环境变量文件服务批量更新配置
            $result = $this->environmentFileService->batchUpdateEnvVars($validatedConfigs);
            
            // 清除缓存
            $this->redis->del('system:config');
            
            // 记录批量配置修改日志
            $this->logger->info('系统配置批量已更新', ['keys' => array_keys($validatedConfigs)]);
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error('批量更新系统配置异常: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 获取用户统计
     *
     * @param array<string, string> $timeRange 时间范围
     * @return int
     */
    protected function getUserCount(array $timeRange): int
    {
        try {
            $conditions = [];
            
            if (isset($timeRange['start'])) {
                $conditions['created_at >='] = $timeRange['start'];
            }
            if (isset($timeRange['end'])) {
                $conditions['created_at <='] = $timeRange['end'];
            }
            
            return $this->userRepository->count($conditions);
        } catch (Exception $e) {
            $this->logger->error('获取用户统计失败: ' . $e->getMessage(), ['timeRange' => $timeRange]);
            return 0;
        }
    }

    /**
     * 获取文章统计
     *
     * @param array<string, string> $timeRange 时间范围
     * @return int
     */
    protected function getArticleCount(array $timeRange): int
    {
        try {
            // 通过注入的BlogRepository获取统计数据
            $conditions = [
                'status' => 1, // 只统计已发布的
            ];
            if (isset($timeRange['start'])) {
                $conditions['created_at_min'] = $timeRange['start'];
            }
            if (isset($timeRange['end'])) {
                $conditions['created_at_max'] = $timeRange['end'];
            }
            return $this->blogRepository->count($conditions);
        } catch (Exception $e) {
            $this->logger->error('获取文章统计失败: ' . $e->getMessage(), ['timeRange' => $timeRange]);
            return 0;
        }
    }

    /**
     * 获取评论统计
     *
     * @param array<string, string> $timeRange 时间范围
     * @return int
     */
    protected function getCommentCount(array $timeRange): int
    {
        try {
            // 通过注入的CommentRepository获取统计数据
            $conditions = [
                'status' => 1, // 只统计已审核通过的
            ];
            if (isset($timeRange['start'])) {
                $conditions['created_at_min'] = $timeRange['start'];
            }
            if (isset($timeRange['end'])) {
                $conditions['created_at_max'] = $timeRange['end'];
            }
            return $this->commentRepository->count($conditions);
        } catch (Exception $e) {
            $this->logger->error('获取评论统计失败: ' . $e->getMessage(), ['timeRange' => $timeRange]);
            return 0;
        }
    }

    /**
     * 获取浏览量统计
     *
     * @param array<string, string> $timeRange 时间范围
     * @return int
     */
    protected function getViewCount(array $timeRange): int
    {
        // 这里假设浏览量存储在redis中
        // 实际项目中可能需要从日志或专门的统计表中获取
        $cacheKey = 'statistics:view_count';
        $totalViews = $this->redis->get($cacheKey);
        return $totalViews ? (int) $totalViews : 0;
    }

    /**
     * 获取最近活动.
     *
     * @param array<string, mixed> $params 查询参数
     * @return array<array<string, mixed>>
     */
    protected function getRecentActivities(array $params): array
    {
        try {
            $limit = $params['limit'] ?? 10;
            
            // 通过ActivityLogRepository获取最近活动
            return $this->activityLogRepository->getRecentActivities($limit);
        } catch (Exception $e) {
            $this->logger->error('获取最近活动失败: ' . $e->getMessage(), ['params' => $params]);
            return [];
        }
    }

    /**
     * 获取每日统计数据.
     *
     * @param array<string, string> $timeRange 时间范围
     * @return array<array<string, mixed>>
     */
    protected function getDailyStats(array $timeRange): array
    {
        // 这里简化处理，实际项目中可能需要更复杂的统计逻辑
        $days = $this->calculateDays($timeRange);
        $stats = [];

        for ($i = 0; $i < $days; ++$i) {
            $date = Carbon::parse($timeRange['start'])->addDays($i);
            $dateStr = $date->format('Y-m-d');

            // 这里应该从每日统计表中获取数据
            // 简化处理，返回空数据
            $stats[] = [
                'date' => $dateStr,
                'user_count' => 0,
                'article_count' => 0,
                'comment_count' => 0,
                'view_count' => 0,
            ];
        }

        return $stats;
    }

    /**
     * 获取时间范围.
     *
     * @param array<string, mixed> $params 查询参数
     * @return array<string, string>
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
     * 计算天数.
     *
     * @param array<string, string> $timeRange 时间范围
     * @return int
     */
    protected function calculateDays(array $timeRange): int
    {
        if (! isset($timeRange['start']) || ! isset($timeRange['end'])) {
            return 30; // 默认30天
        }

        $start = Carbon::parse($timeRange['start']);
        $end = Carbon::parse($timeRange['end']);

        return $start->diffInDays($end) + 1;
    }

    // 权限管理相关功能已移至PermissionService
}
