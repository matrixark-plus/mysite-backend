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

use App\Model\UserAnalytics;
use Exception;
use Hyperf\Database\Model\Collection;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 用户分析数据访问层
 * 封装所有与用户分析数据相关的数据库操作.
 */
class UserAnalyticsRepository
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 根据ID查找用户分析记录.
     *
     * @param int $id 记录ID
     * @return null|UserAnalytics 模型对象或null
     */
    public function findById(int $id): ?UserAnalytics
    {
        try {
            return UserAnalytics::find($id);
        } catch (Exception $e) {
            $this->logger->error('根据ID查找用户分析记录失败: ' . $e->getMessage(), ['analytics_id' => $id]);
            return null;
        }
    }

    /**
     * 根据用户ID获取分析记录列表.
     *
     * @param int $userId 用户ID
     * @param array<string, string> $order 排序条件
     * @return Collection<int, UserAnalytics> 模型集合
     */
    public function findByUserId(int $userId, array $order = ['created_at' => 'desc']): Collection
    {
        try {
            $query = UserAnalytics::where('user_id', $userId);

            foreach ($order as $field => $direction) {
                $query = $query->orderBy($field, $direction);
            }

            $result = $query->get();
            return $result instanceof Collection ? $result : new Collection();
        } catch (Exception $e) {
            $this->logger->error('根据用户ID获取分析记录失败: ' . $e->getMessage(), ['user_id' => $userId]);
            return new Collection();
        }
    }

    /**
     * 根据条件获取用户分析记录列表.
     *
     * @param array<string, mixed> $conditions 查询条件
     * @param array<string> $columns 查询字段
     * @param array<string, string> $order 排序条件
     * @return Collection<int, UserAnalytics> 模型集合
     */
    public function findAllBy(array $conditions = [], array $columns = ['*'], array $order = ['created_at' => 'desc']): Collection
    {
        try {
            $query = UserAnalytics::query();

            if (! empty($conditions)) {
                $query = $query->where($conditions);
            }

            foreach ($order as $field => $direction) {
                $query = $query->orderBy($field, $direction);
            }

            $result = $query->select($columns)->get();
            return $result instanceof Collection ? $result : new Collection();
        } catch (Exception $e) {
            $this->logger->error('获取用户分析记录列表失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return new Collection();
        }
    }

    /**
     * 创建用户分析记录.
     *
     * @param array<string, mixed> $data 分析数据
     * @return null|UserAnalytics 创建的模型对象或null
     */
    public function create(array $data): ?UserAnalytics
    {
        try {
            return UserAnalytics::create($data);
        } catch (Exception $e) {
            $this->logger->error('创建用户分析记录失败: ' . $e->getMessage(), ['data' => $data]);
            return null;
        }
    }

    /**
     * 批量创建用户分析记录.
     *
     * @param array<array<string, mixed>> $dataSet 分析数据数组
     * @return bool 创建是否成功
     */
    public function batchCreate(array $dataSet): bool
    {
        try {
            $result = UserAnalytics::insert($dataSet);
            return $result !== false;
        } catch (Exception $e) {
            $this->logger->error('批量创建用户分析记录失败: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 统计用户分析数据.
     *
     * @param array<string, mixed> $conditions 统计条件
     * @return int 统计结果
     */
    public function count(array $conditions = []): int
    {
        try {
            $query = UserAnalytics::query();

            if (! empty($conditions)) {
                $query = $query->where($conditions);
            }

            return $query->count();
        } catch (Exception $e) {
            $this->logger->error('统计用户分析数据失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return 0;
        }
    }

    /**
     * 清理过期的分析数据.
     *
     * @param string $beforeDate 截止日期
     * @return int 影响的行数
     */
    public function cleanOldData(string $beforeDate): int
    {
        try {
            return UserAnalytics::where('created_at', '<', $beforeDate)->delete();
        } catch (Exception $e) {
            $this->logger->error('清理过期分析数据失败: ' . $e->getMessage(), ['before_date' => $beforeDate]);
            return 0;
        }
    }
}
