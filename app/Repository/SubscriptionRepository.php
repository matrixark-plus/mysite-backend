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

use App\Model\Subscription;
use Exception;
use Hyperf\Database\Model\Collection;
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
     * @return null|Subscription 订阅模型对象或null
     */
    public function findById(int $id): ?Subscription
    {
        try {
            return Subscription::find($id);
        } catch (Exception $e) {
            $this->logger->error('根据ID查找订阅记录失败: ' . $e->getMessage(), ['subscription_id' => $id]);
            return null;
        }
    }

    /**
     * 根据邮箱查找订阅记录.
     *
     * @param string $email 邮箱地址
     * @return null|Subscription 订阅模型对象或null
     */
    public function findByEmail(string $email): ?Subscription
    {
        try {
            return Subscription::where('email', $email)->first();
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
     * @return null|Subscription 订阅模型对象或null
     */
    public function findBy(array $conditions, array $columns = ['*']): ?Subscription
    {
        try {
            $result = Subscription::where($conditions)->first($columns);
            return $result instanceof Subscription ? $result : null;
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
     * @return Collection<int, Subscription> 订阅模型集合
     */
    public function findAllBy(array $conditions = [], array $columns = ['*'], array $order = ['created_at' => 'desc']): Collection
    {
        try {
            $query = Subscription::query();

            if (! empty($conditions)) {
                $query = $query->where($conditions);
            }

            foreach ($order as $field => $direction) {
                $query = $query->orderBy($field, $direction);
            }

            $result = $query->select($columns)->get();
            return $result instanceof Collection ? $result : new Collection();
        } catch (Exception $e) {
            $this->logger->error('获取订阅记录列表失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return new Collection();
        }
    }

    /**
     * 创建订阅记录.
     *
     * @param array<string, mixed> $data 订阅数据
     * @return null|Subscription 创建的订阅模型对象或null
     */
    public function create(array $data): ?Subscription
    {
        try {
            return Subscription::create($data);
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
            $result = Subscription::where('id', $id)->update($data);
            return $result > 0;
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
            $result = Subscription::destroy($id);
            return $result > 0;
        } catch (Exception $e) {
            $this->logger->error('删除订阅记录失败: ' . $e->getMessage(), ['subscription_id' => $id]);
            return false;
        }
    }
}