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
class SubscribeRepository
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
     * @return null|array 订阅数据数组或null
     */
    public function findById(int $id): ?array
    {
        try {
            $result = Db::table('subscribes')
                ->where('id', $id)
                ->first();
            return $result ? (array) $result : null;
        } catch (Exception $e) {
            $this->logger->error('根据ID查找订阅记录失败: ' . $e->getMessage(), ['subscribe_id' => $id]);
            return null;
        }
    }

    /**
     * 根据邮箱查找订阅记录.
     *
     * @param string $email 邮箱地址
     * @param string $type 订阅类型
     * @return null|array 订阅数据数组或null
     */
    public function findByEmail(string $email, string $type = 'blog'): ?array
    {
        try {
            $result = Db::table('subscribes')
                ->where('email', $email)
                ->where('type', $type)
                ->first();
            return $result ? (array) $result : null;
        } catch (Exception $e) {
            $this->logger->error('根据邮箱查找订阅记录失败: ' . $e->getMessage(), ['email' => $email, 'type' => $type]);
            return null;
        }
    }

    /**
     * 根据token查找订阅记录.
     *
     * @param string $token 验证token
     * @return null|array 订阅数据数组或null
     */
    public function findByToken(string $token): ?array
    {
        try {
            $result = Db::table('subscribes')
                ->where('token', $token)
                ->where('status', 'pending')
                ->first();
            return $result ? (array) $result : null;
        } catch (Exception $e) {
            $this->logger->error('根据token查找订阅记录失败: ' . $e->getMessage(), ['token' => $token]);
            return null;
        }
    }

    /**
     * 获取已确认的订阅者列表.
     *
     * @param string $type 订阅类型
     * @return array 邮箱地址数组
     */
    public function getConfirmedSubscribers(string $type = Subscribe::TYPE_BLOG): array
    {
        try {
            $result = Subscribe::where('type', $type)
                ->where('status', Subscribe::STATUS_CONFIRMED)
                ->pluck('email')
                ->toArray();
            return $result;
        } catch (Exception $e) {
            $this->logger->error('获取已确认订阅者列表失败: ' . $e->getMessage(), ['type' => $type]);
            return [];
        }
    }

    /**
     * 创建订阅记录.
     *
     * @param array<string, mixed> $data 订阅数据
     * @return null|array 创建的订阅数据数组或null
     */
    public function create(array $data): ?array
    {
        try {
            $id = Db::table('subscribes')->insertGetId($data);
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
            $result = Db::table('subscribes')
                ->where('id', $id)
                ->update($data);
            return $result > 0;
        } catch (Exception $e) {
            $this->logger->error('更新订阅记录失败: ' . $e->getMessage(), ['subscribe_id' => $id, 'data' => $data]);
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
            $result = Db::table('subscribes')
                ->where('id', $id)
                ->delete();
            return $result > 0;
        } catch (Exception $e) {
            $this->logger->error('删除订阅记录失败: ' . $e->getMessage(), ['subscribe_id' => $id]);
            return false;
        }
    }
}