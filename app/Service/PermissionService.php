<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;
use App\Repository\UserRepository;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerInterface;

/**
 * 权限管理服务类
 * 封装所有与权限和角色相关的业务逻辑
 */
class PermissionService
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * @Inject
     * @var UserRepository
     */
    protected $userRepository;
    
    /**
     * 获取所有角色列表
     * 
     * @return array 角色列表
     */
    public function getAllRoles(): array
    {
        // 基于角色的权限管理模型定义的角色列表
        $roles = [
            ['value' => 'admin', 'label' => '管理员', 'description' => '具有所有权限'],
            ['value' => 'editor', 'label' => '编辑', 'description' => '具有内容编辑权限'],
            ['value' => 'user', 'label' => '普通用户', 'description' => '基础用户权限']
        ];
        
        return $roles;
    }
    
    /**
     * 获取用户角色信息
     * 
     * @param int $userId 用户ID
     * @return array 用户角色信息
     * @throws \Exception 当用户不存在时抛出异常
     */
    public function getUserRoleInfo(int $userId): array
    {
        $user = $this-\u003euserRepository-\u003efindById($userId);
        if (!$user) {
            throw new \Exception('用户不存在');
        }
        
        $roles = $this->getAllRoles();
        $currentRole = null;
        
        foreach ($roles as $role) {
            if ($role['value'] === $user->role) {
                $currentRole = $role;
                break;
            }
        }
        
        return [
            'user_id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'role_info' => $currentRole,
            'created_at' => $user->created_at
        ];
    }
    
    /**
     * 更新用户角色
     * 
     * @param int $userId 用户ID
     * @param string $role 新角色
     * @return bool 是否更新成功
     * @throws \Exception 当角色无效、用户不存在或尝试移除最后一个管理员时抛出异常
     */
    public function updateUserRole(int $userId, string $role): bool
    {
        // 验证角色是否有效
        $validRoles = array_column($this-\u003egetAllRoles(), 'value');
        if (!in_array($role, $validRoles)) {
            throw new \Exception('无效的角色类型');
        }
        
        $user = $this-\u003euserRepository-\u003efindById($userId);
        if (!$user) {
            throw new \Exception('用户不存在');
        }
        
        // 不允许将最后一个管理员降级
        if ($user-\u003erole === 'admin' \u0026\u0026 $role !== 'admin') {
            $adminCount = $this-\u003euserRepository-\u003egetAdminCount();
            if ($adminCount \u003c= 1) {
                throw new \Exception('不允许移除最后一个管理员角色');
            }
        }
        
        // 记录角色变更日志
        $oldRole = $user-\u003erole; // 在更新前保存旧角色
        
        try {
            // 使用Repository更新用户角色
            $result = $this-\u003euserRepository-\u003eupdateRole($userId, $role);
            
            $this-\u003elogger-\u003einfo('用户角色已更新', [
                'user_id' =\u003e $userId,
                'old_role' =\u003e $oldRole,
                'new_role' =\u003e $role
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $this-\u003elogger-\u003eerror('更新用户角色失败: ' . $e-\u003egetMessage(), [
                'user_id' =\u003e $userId,
                'role' =\u003e $role
            ]);
            throw $e;
        }
    }
    
    /**
     * 检查用户是否具有指定角色
     * 
     * @param User $user 用户模型
     * @param string $role 角色名称
     * @return bool 是否具有指定角色
     */
    public function hasRole(User $user, string $role): bool
    {
        return $user->role === $role;
    }
    
    /**
     * 检查用户是否为管理员
     * 
     * @param User $user 用户模型
     * @return bool 是否为管理员
     */
    public function isAdmin(User $user): bool
    {
        return $this->hasRole($user, 'admin');
    }
    
    /**
     * 检查用户是否为编辑或更高权限
     * 
     * @param User $user 用户模型
     * @return bool 是否为编辑或管理员
     */
    public function isEditorOrAbove(User $user): bool
    {
        return in_array($user->role, ['admin', 'editor']);
    }
}