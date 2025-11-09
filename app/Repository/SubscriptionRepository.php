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
 * 订阅数据访问层
 * 封装所有与订阅数据相关的数据库操作.
 */
class SubscriptionRepository
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 根据ID查找订阅记录.
     *
     * @param int $id 订阅记录ID
     * @return array|null 订阅数据数组或null
     */
    public function findById(int $id): ?array
    {
        try {
            $result = Db::table('subscriptions')->find($id);
            return is_object($result) ? (array)$result : $result;
        } catch (Exception $e) {
            $this->logger->error('根据ID查找订阅记录失败: ' . $e->getMessage(), ['subscription_id' => $id]);
            return null;
        }
    }

    /**
     * 根据邮箱查找订阅记录.
     *
     * @param string $email 邮箱地址
     * @return array|null 订阅数据数组或null
     */
    public function findByEmail(string $email): ?array
    {
        try {
            $result = Db::table('subscriptions')->where('email', $email)->first();
            return is_object($result) ? (array)$result : $result;
        } catch (Exception $e) {
            $this->logger->error('根据邮箱查找订阅记录失败: ' . $e->getMessage(), ['email' => $email]);
            return null;
        }
    }

    /**
     * 根据条件查询订阅记录.
     *
     * @param array<string, mixed> $conditions 查询条件
     * @param array<string> $columns 查询字段
     * @return array|null 订阅数据数组或null
     */
    public function findBy(array $conditions, array $columns = ['*']): ?array
    {
        try {
            $query = Db::table('subscriptions');
            
            foreach ($conditions as $key => $value) {
                // 处理复杂条件如OR
                if ($key === 'OR' && is_array($value)) {
                    $query = $query->where(function ($q) use ($value) {
                        foreach ($value as $orCondition) {
                            $q->orWhere(...$orCondition);
                        }
                    });
                } else {
                    $query = $query->where($key, $value);
                }
            }
            
            $result = $query->select($columns)->first();
            return is_object($result) ? (array)$result : $result;
        } catch (Exception $e) {
            $this->logger->error('根据条件查询订阅记录失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return null;
        }
    }

    /**
     * 根据条件获取订阅记录列表.
     *
     * @param array<string, mixed> $conditions 查询条件
     * @param array<string> $columns 查询字段
     * @param array<string, string> $order 排序条件
     * @return array 订阅数据数组
     */
    public function findAllBy(array $conditions = [], array $columns = ['*'], array $order = ['created_at' => 'desc']): array
    {
        try {
            $query = Db::table('subscriptions');

            if (! empty($conditions)) {
                foreach ($conditions as $key => $value) {
                    // 处理复杂条件如OR
                    if ($key === 'OR' && is_array($value)) {
                        $query = $query->where(function ($q) use ($value) {
                            foreach ($value as $orCondition) {
                                $q->orWhere(...$orCondition);
                            }
                        });
                    } else {
                        $query = $query->where($key, $value);
                    }
                }
            }

            foreach ($order as $field => $direction) {
                $query = $query->orderBy($field, $direction);
            }

            return $query->select($columns)->get()->toArray();
        } catch (Exception $e) {
            $this->logger->error('获取订阅记录列表失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return [];
        }
    }

    /**
     * 创建订阅记录.
     *
     * @param array<string, mixed> $data 订阅数据
     * @return array|null 创建的订阅数据数组或null
     */
    public function create(array $data): ?array
    {
        try {
            $id = Db::table('subscriptions')->insertGetId($data);
            return $this->findById($id);
        } catch (Exception $e) {
            $this->logger->error('创建订阅记录失败: ' . $e->getMessage(), ['data' => $data]);
            return null;
        }
    }

    /**
     * 更新订阅记录.
     *
     * @param int $id 订阅记录ID
     * @param array<string, mixed> $data 更新数据
     * @return bool 更新是否成功
     */
    public function update(int $id, array $data): bool
    {
        try {
            return Db::transaction(function () use ($id, $data) {
                $result = Db::table('subscriptions')->where('id', $id)->update($data);
                return $result > 0;
            });
        } catch (Exception $e) {
            $this->logger->error('更新订阅记录失败: ' . $e->getMessage(), ['subscription_id' => $id, 'data' => $data]);
            return false;
        }
    }

    /**
     * 删除订阅记录.
     *
     * @param int $id 订阅记录ID
     * @return bool 删除是否成功
     */
    public function delete(int $id): bool
    {
        try {
            return Db::transaction(function () use ($id) {
                $result = Db::table('subscriptions')->where('id', $id)->delete();
                return $result > 0;
            });
        } catch (Exception $e) {
            $this->logger->error('删除订阅记录失败: ' . $e->getMessage(), ['subscription_id' => $id]);
            return false;
        }
    }
}