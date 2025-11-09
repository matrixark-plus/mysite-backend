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
     * @return array|null 用户数据数组或null
     */
    public function findById(int $id): ?array
    {
        try {
            $result = Db::table('users')->find($id);
            return is_object($result) ? (array)$result : $result;
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
     * @return array|null 用户数据数组或null
     */
    public function findBy(array $conditions, array $columns = ['*']): ?array
    {
        try {
            $query = Db::table('users');
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
            $this->logger->error('根据条件查询用户失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return null;
        }
    }
    
    /**
     * 根据用户名查询用户
     * @param string $username 用户名
     * @return array|null 用户数据
     */
    public function findByUsername(string $username): ?array
    {
        return $this->findBy(['username' => $username]);
    }
    
    /**
     * 更新用户密码
     * @param int $userId 用户ID
     * @param string $password 新密码
     * @return bool 是否成功
     */
    public function updatePassword(int $userId, string $password): bool
    {
        try {
            return User::where('id', $userId)->update([
                'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                'updated_at' => date('Y-m-d H:i:s')
            ]) > 0;
        } catch (Exception $e) {
            $this->logger->error('更新用户密码失败', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * 更新登录信息
     * @param int $userId 用户ID
     * @param string $loginIp IP地址
     * @return bool 是否成功
     */
    public function updateLoginInfo(int $userId, string $loginIp): bool
    {
        try {
            return User::where('id', $userId)->update([
                'last_login_ip' => $loginIp,
                'last_login_at' => date('Y-m-d H:i:s')
            ]) > 0;
        } catch (Exception $e) {
            $this->logger->error('更新登录信息失败', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * 锁定账号
     * @param int $userId 用户ID
     * @return bool 是否成功
     */
    public function lockAccount(int $userId): bool
    {
        try {
            return User::where('id', $userId)->update([
                'status' => 0,
                'lock_expire_time' => date('Y-m-d H:i:s', time() + 1800), // 30分钟后过期
                'updated_at' => date('Y-m-d H:i:s')
            ]) > 0;
        } catch (Exception $e) {
            $this->logger->error('锁定账号失败', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * 重置失败尝试次数
     * @param int $userId 用户ID
     * @return bool 是否成功
     */
    public function resetFailedAttempts(int $userId): bool
    {
        try {
            return User::where('id', $userId)->update([
                'failed_login_attempts' => 0,
                'is_locked' => false,
                'lock_expire_time' => null
            ]) > 0;
        } catch (Exception $e) {
            $this->logger->error('重置失败尝试次数失败', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 根据条件获取用户列表.
     *
     * @param array<string, mixed> $conditions 查询条件
     * @param array<string> $columns 查询字段
     * @param array<string, string> $order 排序条件
     * @return array 用户数据数组
     */
    public function findAllBy(array $conditions = [], array $columns = ['*'], array $order = ['created_at' => 'desc']): array
    {
        try {
            $query = Db::table('users');

            // 处理查询条件
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
            $this->logger->error('获取用户列表失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return [];
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
            $query = Db::table('users');

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

            return (int)$query->count();
        } catch (Exception $e) {
            $this->logger->error('统计用户数量失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return 0;
        }
    }

    /**
     * 创建用户.
     *
     * @param array<string, mixed> $data 用户数据
     * @return array|null 创建的用户数据数组或null
     */
    public function create(array $data): ?array
    {
        try {
            $result = Db::transaction(function () use ($data) {
                $user = new User();
                foreach ($data as $key => $value) {
                    if (property_exists($user, $key)) {
                        $user->{$key} = $value;
                    }
                }

                // 确保注册时间戳
                if (! isset($data['created_at'])) {
                    $user->created_at = date('Y-m-d H:i:s');
                }

                $user->save();
                return $user;
            });
            
            // 转换为数组返回
            return $result instanceof User ? $result->toArray() : null;
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
                // 不再需要updated_at字段

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
     * 获取管理员用户数量.
     *
     * @return int 管理员数量
     */
    public function getAdminCount(): int
    {
        return $this->count(['is_admin' => true]);
    }
}
