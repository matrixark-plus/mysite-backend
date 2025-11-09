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

use App\Repository\UserRepository;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 权限管理服务类
 * 封装所有与权限和角色相关的业务逻辑.
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
     * 获取权限相关信息.
     *
     * @return array 权限信息列表
     */
    public function getAllRoles(): array
    {
        // 系统现在使用is_admin布尔字段而非角色系统
        // 保留此方法以兼容旧代码
        return [
            ['label' => '普通用户', 'value' => 'user'],
            ['label' => '管理员', 'value' => 'admin'],
        ];
    }

    /**
     * 获取用户权限信息.
     *
     * @param int $userId 用户ID
     * @return array<string, mixed> 用户权限信息
     * @throws Exception 当用户不存在时抛出异常
     */
    public function getUserRoleInfo(int $userId): array
    {
        $user = $this->userRepository->findById($userId);
        if (! $user) {
            throw new Exception('用户不存在');
        }

        return [
            'user_id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'is_admin' => $user->is_admin ?? false,
            'created_at' => $user->created_at,
        ];
    }

    /**
     * 更新用户角色.
     *
     * @param int $userId 用户ID
     * @param string $role 新角色
     * @return bool 是否更新成功
     * @throws Exception 当角色无效、用户不存在或尝试移除最后一个管理员时抛出异常
     */
    public function updateUserRole(int $userId, string $role): bool
    {
        // 验证角色是否有效
        $validRoles = array_column($this->getAllRoles(), 'value');
        if (! in_array($role, $validRoles)) {
            throw new Exception('无效的角色类型');
        }

        $user = $this->userRepository->findById($userId);
        if (! $user) {
            throw new Exception('用户不存在');
        }

        // 不允许将最后一个管理员降级
        if ($user->role === 'admin' && $role !== 'admin') {
            $adminCount = $this->userRepository->getAdminCount();
            if ($adminCount <= 1) {
                throw new Exception('不允许移除最后一个管理员角色');
            }
        }

        // 记录角色变更日志
        $oldRole = $user->role; // 在更新前保存旧角色

        try {
            // 使用Repository更新用户角色
            $result = $this->userRepository->updateRole($userId, $role);

            $this->logger->info('用户角色已更新', [
                'user_id' => $userId,
                'old_role' => $oldRole,
                'new_role' => $role,
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('更新用户角色失败: ' . $e->getMessage(), [
                'user_id' => $userId,
                'role' => $role,
            ]);
            throw $e;
        }
    }

    /**
     * 检查用户是否具有管理员权限.
     *
     * @param \App\Model\User|object $user 用户对象
     * @param string $role 角色名称（保留参数以兼容旧代码）
     * @return bool 是否具有管理员权限
     */
    public function hasRole($user, string $role): bool
    {
        // 确保是User对象
        if (! $user instanceof \App\Model\User) {
            $this->logger->error('Expected User object', ['actual_type' => gettype($user)]);
            return false;
        }
        
        // 如果请求的是管理员角色，检查is_admin字段
        if ($role === 'admin') {
            return $user->is_admin ?? false;
        }
        
        return false;
    }

    /**
     * 检查用户是否为管理员.
     *
     * @param \App\Model\User|object $user 用户对象
     * @return bool 是否为管理员
     */
    public function isAdmin($user): bool
    {
        return $this->hasRole($user, 'admin');
    }

    /**
     * 检查用户是否为编辑或更高权限.
     *
     * @param \App\Model\User|object $user 用户对象
     * @return bool 是否为编辑或管理员
     */
    public function isEditorOrAbove($user): bool
    {
        // 确保是User对象
        if (! $user instanceof \App\Model\User) {
            $this->logger->error('Expected User object', ['actual_type' => gettype($user)]);
            return false;
        }
        return in_array($user->role, ['admin', 'editor']);
    }
}
