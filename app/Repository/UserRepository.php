<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\User;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerInterface;

/**
 * 用户数据访问层
 * 封装所有与用户数据相关的数据库操作
 */
class UserRepository
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * 根据ID查找用户
     * 
     * @param int $id 用户ID
     * @return array|null 用户数据数组或null
     */
    public function findById(int $id): ?array
    {
        try {
            $user = User::find($id);
            return $user ? $user->toArray() : null;
        } catch (\Exception $e) {
            $this->logger->error('根据ID查找用户失败: ' . $e->getMessage(), ['user_id' => $id]);
            return null;
        }
    }
    
    /**
     * 根据条件查询用户
     * 
     * @param array $conditions 查询条件
     * @param array $columns 查询字段
     * @return array|null 用户数据数组或null
     */
    public function findBy(array $conditions, array $columns = ['*']): ?array
    {
        try {
            $user = User::where($conditions)->first($columns);
            return $user ? $user->toArray() : null;
        } catch (\Exception $e) {
            $this->logger->error('根据条件查询用户失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return null;
        }
    }
    
    /**
     * 根据条件获取用户列表
     * 
     * @param array $conditions 查询条件
     * @param array $columns 查询字段
     * @param array $order 排序条件
     * @return array 用户数据数组
     */
    public function findAllBy(array $conditions = [], array $columns = ['*'], array $order = ['created_at' => 'desc']): array
    {
        try {
            $query = User::query();
            
            if (!empty($conditions)) {
                $query = $query->where($conditions);
            }
            
            foreach ($order as $field => $direction) {
                $query = $query->orderBy($field, $direction);
            }
            
            $users = $query->select($columns)-u003eget();
            return $users->toArray();
        } catch (\Exception $e) {
            $this->logger->error('获取用户列表失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return [];
        }
    }
    
    /**
     * 统计符合条件的用户数量
     * 
     * @param array $conditions 查询条件
     * @return int 用户数量
     */
    public function count(array $conditions = []): int
    {
        try {
            $query = User::query();
            
            if (!empty($conditions)) {
                $query = $query->where($conditions);
            }
            
            return $query->count();
        } catch (\Exception $e) {
            $this->logger->error('统计用户数量失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return 0;
        }
    }
    
    /**
     * 创建用户
     * 
     * @param array $data 用户数据
     * @return array|null 创建的用户数据数组或null
     */
    public function create(array $data): ?array
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
                if (!isset($data['created_at'])) {
                    $user->created_at = date('Y-m-d H:i:s');
                }
                if (!isset($data['updated_at'])) {
                    $user->updated_at = date('Y-m-d H:i:s');
                }
                
                $user->save();
                return $user->toArray();
            });
        } catch (\Exception $e) {
            $this->logger->error('创建用户失败: ' . $e->getMessage(), ['data' => $data]);
            return null;
        }
    }
    
    /**
     * 更新用户
     * 
     * @param int $id 用户ID
     * @param array $data 更新数据
     * @return bool 是否更新成功
     */
    public function update(int $id, array $data): bool
    {
        try {
            return Db::transaction(function () use ($id, $data) {
                // 确保更新时间戳
                if (!isset($data['updated_at'])) {
                    $data['updated_at'] = date('Y-m-d H:i:s');
                }
                
                return (bool)User::where('id', $id)->update($data);
            });
        } catch (\Exception $e) {
            $this->logger->error('更新用户失败: ' . $e->getMessage(), [
                'user_id' => $id,
                'data' => $data
            ]);
            return false;
        }
    }
    
    /**
     * 更新用户角色
     * 
     * @param int $userId 用户ID
     * @param string $role 新角色
     * @return bool 是否更新成功
     */
    public function updateRole(int $userId, string $role): bool
    {
        try {
            return Db::transaction(function () use ($userId, $role) {
                return User::where('id', $userId)-u003eupdate([
                    'role' => $role,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            });
        } catch (\Exception $e) {
            $this->logger->error('更新用户角色失败: ' . $e->getMessage(), [
                'user_id' => $userId,
                'role' => $role
            ]);
            return false;
        }
    }
    
    /**
     * 获取管理员用户数量
     * 
     * @return int 管理员数量
     */
    public function getAdminCount(): int
    {
        return $this->count(['role' => 'admin']);
    }
}