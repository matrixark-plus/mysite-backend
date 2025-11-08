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

use App\Model\User;
use Exception;
use Hyperf\Database\Model\Collection;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 用户数据访问层
 * 封装所有与用户数据相关的数据库操作.
 */
class UserRepository
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 根据ID查找用户.
     *
     * @param int $id 用户ID
     * @return null|User 用户模型对象或null
     */
    public function findById(int $id): ?User
    {
        try {
            return User::find($id);
        } catch (Exception $e) {
            $this->logger->error('根据ID查找用户失败: ' . $e->getMessage(), ['user_id' => $id]);
            return null;
        }
    }

    /**
     * 根据条件查询用户.
     *
     * @param array<string, mixed> $conditions 查询条件
     * @param array<string> $columns 查询字段
     * @return null|User 用户模型对象或null
     */
    public function findBy(array $conditions, array $columns = ['*']): ?User
    {
        try {
            $result = User::where($conditions)->first($columns);
            return $result instanceof User ? $result : null;
        } catch (Exception $e) {
            $this->logger->error('根据条件查询用户失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return null;
        }
    }

    /**
     * 根据条件获取用户列表.
     *
     * @param array<string, mixed> $conditions 查询条件
     * @param array<string> $columns 查询字段
     * @param array<string, string> $order 排序条件
     * @return \Hyperf\Database\Model\Collection<int, \App\Model\User> 用户模型集合
     */
    public function findAllBy(array $conditions = [], array $columns = ['*'], array $order = ['created_at' => 'desc']): \Hyperf\Database\Model\Collection
    {
        try {
            $query = User::query();

            if (! empty($conditions)) {
                $query = $query->where($conditions);
            }

            foreach ($order as $field => $direction) {
                $query = $query->orderBy($field, $direction);
            }

            $result = $query->select($columns)->get();
            return $result instanceof \Hyperf\Database\Model\Collection ? $result : new \Hyperf\Database\Model\Collection();
        } catch (Exception $e) {
            $this->logger->error('获取用户列表失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return new \Hyperf\Database\Model\Collection();
        }
    }

    /**
     * 统计符合条件的用户数量.
     *
     * @param array<string, mixed> $conditions 查询条件
     * @return int 用户数量
     */
    public function count(array $conditions = []): int
    {
        try {
            $query = User::query();

            if (! empty($conditions)) {
                $query = $query->where($conditions);
            }

            return $query->count();
        } catch (Exception $e) {
            $this->logger->error('统计用户数量失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return 0;
        }
    }

    /**
     * 创建用户.
     *
     * @param array<string, mixed> $data 用户数据
     * @return null|User 创建的用户模型对象或null
     */
    public function create(array $data): ?User
    {
        try {
            return Db::transaction(function () use ($data) {
                $user = new User();
                foreach ($data as $key => $value) {
                    if (property_exists($user, $key)) {
                        $user->{$key} = $value;
                    }
                }

                // 确保时间戳
                if (! isset($data['created_at'])) {
                    $user->created_at = date('Y-m-d H:i:s');
                }
                if (! isset($data['updated_at'])) {
                    $user->updated_at = date('Y-m-d H:i:s');
                }

                $user->save();
                return $user;
            });
        } catch (Exception $e) {
            $this->logger->error('创建用户失败: ' . $e->getMessage(), ['data' => $data]);
            return null;
        }
    }

    /**
     * 更新用户.
     *
     * @param int $id 用户ID
     * @param array<string, mixed> $data 更新数据
     * @return bool 是否更新成功
     */
    public function update(int $id, array $data): bool
    {
        try {
            return Db::transaction(function () use ($id, $data) {
                // 确保更新时间戳
                if (! isset($data['updated_at'])) {
                    $data['updated_at'] = date('Y-m-d H:i:s');
                }

                return (bool) User::where('id', $id)->update($data);
            });
        } catch (Exception $e) {
            $this->logger->error('更新用户失败: ' . $e->getMessage(), [
                'user_id' => $id,
                'data' => $data,
            ]);
            return false;
        }
    }

    /**
     * 更新用户角色.
     *
     * @param int $userId 用户ID
     * @param string $role 新角色
     * @return bool 是否更新成功
     */
    public function updateRole(int $userId, string $role): bool
    {
        try {
            return Db::transaction(function () use ($userId, $role) {
                return User::where('id', $userId)->update([
                    'role' => $role,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            });
        } catch (Exception $e) {
            $this->logger->error('更新用户角色失败: ' . $e->getMessage(), [
                'user_id' => $userId,
                'role' => $role,
            ]);
            return false;
        }
    }

    /**
     * 获取管理员用户数量.
     *
     * @return int 管理员数量
     */
    public function getAdminCount(): int
    {
        return $this->count(['role' => 'admin']);
    }
}
