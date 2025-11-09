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

use App\Model\User;
use App\Repository\UserRepository;
use Exception;
use Hyperf\Di\Annotation\Inject;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * 用户服务
 * 封装用户相关的业务逻辑.
 */
class UserService
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
     * 创建新用户.
     *
     * @param array<string, mixed> $data 用户数据
     * @return array<string, mixed> 创建的用户信息数组
     * @throws InvalidArgumentException
     */
    public function createUser(array $data): array
    {
        // 验证数据
        $this->validateUserData($data);

        // 检查邮箱是否已存在
        if ($this->getUserByEmail($data['email'])) {
            throw new InvalidArgumentException('邮箱已被注册');
        }

        // 准备数据
        $userData = [
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'is_active' => $data['is_active'] ?? false,
            'is_admin' => $data['is_admin'] ?? false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // 设置可选字段
        if (isset($data['real_name'])) {
            $userData['real_name'] = $data['real_name'];
        }
        if (isset($data['avatar'])) {
            $userData['avatar'] = $data['avatar'];
        }
        if (isset($data['bio'])) {
            $userData['bio'] = $data['bio'];
        }

        try {
            // 返回repository创建的用户模型
            $user = $this->userRepository->create($userData);
            if (! $user) {
                throw new Exception('创建用户失败');
            }
            // 将User对象转换为数组返回，符合Repository层返回数组的规范
            return $this->userToArray($user);
        } catch (Exception $e) {
            $this->logger->error('创建用户失败: ' . $e->getMessage(), ['data' => $data]);
            throw $e;
        }
    }

    /**
     * 根据ID获取用户.
     *
     * @param int $id 用户ID
     * @return array<string, mixed>|null 用户信息数组或null
     */
    public function getUserById(int $id): ?array
    {
        $user = $this->userRepository->findById($id);
        return $user ? $this->userToArray($user) : null;
    }

    /**
     * 根据邮箱获取用户.
     *
     * @param string $email 邮箱
     * @return array<string, mixed>|null 用户信息数组或null
     */
    public function getUserByEmail(string $email): ?array
    {
        $user = $this->userRepository->findBy(['email' => $email]);
        return $user ? $this->userToArray($user) : null;
    }

    /**
     * 根据用户名获取用户.
     *
     * @param string $username 用户名
     * @return array<string, mixed>|null 用户信息数组或null
     */
    /**
     * 验证用户凭证
     *
     * @param string $email 邮箱
     * @param string $password 密码
     * @param string $loginIp 登录IP地址
     * @return array<string, mixed>|null 验证通过的用户信息数组或null
     * @throws \Exception 当用户账号被锁定时抛出异常
     */
    public function validateCredentials(string $email, string $password, string $loginIp = ''): ?array
    {
        $userData = $this->getUserByEmail($email);

        if (! $userData) {
            return null;
        }

        // 检查用户账号是否被锁定
        if (isset($userData['is_locked']) && $userData['is_locked']) {
            $lockExpireTime = $userData['lock_expire_time'] ?? 0;
            if ($lockExpireTime > time()) {
                $lockTime = ceil(($lockExpireTime - time()) / 60);
                throw new \Exception("账号已被锁定，请{$lockTime}分钟后再试");
            } else {
                // 锁定时间已过期，解锁账号
                $this->unlockUser($userData['id']);
                $userData['is_locked'] = false;
                $userData['login_attempts'] = 0;
            }
        }

        // 检查密码是否正确
        if (! password_verify($password, $userData['password_hash'] ?? '')) {
            // 记录登录失败次数，包含IP信息
            $this->recordLoginFailure($userData['id'], $loginIp);
            return null;
        }

        // 检查用户状态
        if (! $userData['is_active']) {
            return null;
        }

        // 登录成功，重置失败次数并更新登录信息
        $this->resetLoginAttempts($userData['id']);
        $this->updateLoginInfo($userData['id'], $loginIp);

        return $userData;
    }

    /**
     * 记录登录失败
     *
     * @param int $userId 用户ID
     * @param string $loginIp 登录IP地址
     */
    protected function recordLoginFailure(int $userId, string $loginIp): void
    {
        try {
            // 获取当前登录失败次数
            $userData = $this->userRepository->findById($userId);
            if (! $userData) {
                return;
            }

            $loginAttempts = ($userData['login_attempts'] ?? 0) + 1;
            $updateData = ['login_attempts' => $loginAttempts];

            // 如果失败次数达到5次，锁定账号30分钟
            if ($loginAttempts >= 5) {
                $updateData['is_locked'] = true;
                $updateData['lock_expire_time'] = time() + 30 * 60; // 30分钟后解锁
                $this->logger->warning('用户账号被锁定', ['user_id' => $userId, 'attempts' => $loginAttempts, 'ip' => $loginIp]);
            }

            // 更新用户信息
            $this->userRepository->update($userId, $updateData);
            $this->logger->info('记录登录失败', ['user_id' => $userId, 'attempts' => $loginAttempts, 'ip' => $loginIp]);
        } catch (\Exception $e) {
            $this->logger->error('记录登录失败异常', ['user_id' => $userId, 'ip' => $loginIp, 'error' => $e->getMessage()]);
        }
    }

    /**
     * 更新用户登录信息
     *
     * @param int $userId 用户ID
     * @param string $loginIp 登录IP地址
     */
    protected function updateLoginInfo(int $userId, string $loginIp): void
    {
        try {
            $updateData = [
                'last_login_at' => date('Y-m-d H:i:s'),
                'last_login_ip' => $loginIp
            ];
            $this->userRepository->update($userId, $updateData);
            $this->logger->info('更新登录信息成功', ['user_id' => $userId, 'ip' => $loginIp]);
        } catch (\Exception $e) {
            $this->logger->error('更新登录信息异常', ['user_id' => $userId, 'ip' => $loginIp, 'error' => $e->getMessage()]);
        }
    }

    /**
     * 重置登录失败次数
     *
     * @param int $userId 用户ID
     */
    protected function resetLoginAttempts(int $userId): void
    {
        try {
            $this->userRepository->update($userId, ['login_attempts' => 0]);
        } catch (\Exception $e) {
            $this->logger->error('重置登录失败次数异常', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * 解锁用户账号
     *
     * @param int $userId 用户ID
     */
    public function unlockUser(int $userId): void
    {
        try {
            $this->userRepository->update($userId, [
                'is_locked' => false,
                'lock_expire_time' => null,
                'login_attempts' => 0
            ]);
            $this->logger->info('用户账号已解锁', ['user_id' => $userId]);
        } catch (\Exception $e) {
            $this->logger->error('解锁用户账号异常', ['user_id' => $userId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 用户登录流程.
     *
     * @param string $email 邮箱
     * @param string $password 密码
     * @param string $loginIp 登录IP地址
     * @return array<string, mixed> 登录成功的用户信息数组
     * @throws InvalidArgumentException
     */
    public function login(string $email, string $password, string $loginIp = ''): array
    {
        // 参数验证
        if (empty($email) || empty($password)) {
            throw new InvalidArgumentException('邮箱和密码不能为空');
        }

        // 邮箱格式验证
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('邮箱格式不正确');
        }

        // 验证用户凭据，传入IP信息
        $userData = $this->validateCredentials($email, $password, $loginIp);

        if (! $userData) {
            throw new InvalidArgumentException('邮箱或密码错误');
        }

        return $userData;
    }

    /**
     * 更新用户信息.
     *
     * @param int $id 用户ID
     * @param array<string, mixed> $data 更新数据
     * @return array<string, mixed> 更新后的用户信息数组
     * @throws InvalidArgumentException
     */
    public function updateUser(int $id, array $data): array
    {
        // 检查用户是否存在
        $userData = $this->getUserById($id);
        if (! $userData) {
            throw new InvalidArgumentException('用户不存在');
        }

        // 准备更新数据
        $updateData = [];
        if (isset($data['real_name'])) {
            $updateData['real_name'] = $data['real_name'];
        }
        if (isset($data['avatar'])) {
            $updateData['avatar'] = $data['avatar'];
        }
        if (isset($data['bio'])) {
            $updateData['bio'] = $data['bio'];
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }
        if (isset($data['is_admin'])) {
            $updateData['is_admin'] = $data['is_admin'];
        }

        // 如果没有需要更新的数据，直接返回原用户数据
        if (empty($updateData)) {
            return $userData;
        }

        // 不再需要updated_at字段

        try {
            $result = $this->userRepository->update($id, $updateData);
            if ($result) {
                $updatedUser = $this->getUserById($id);
                if (! $updatedUser) {
                    throw new Exception('获取更新后的用户信息失败');
                }
                return $updatedUser;
            }
            throw new Exception('更新用户失败');
        } catch (Exception $e) {
            $this->logger->error('更新用户信息失败: ' . $e->getMessage(), ['id' => $id, 'data' => $data]);
            throw $e;
        }
    }

    /**
     * 修改用户密码
     *
     * @param int $userId 用户ID
     * @param string $currentPassword 当前密码
     * @param string $newPassword 新密码
     * @return bool 修改密码是否成功
     * @throws InvalidArgumentException
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        // 参数验证
        if (empty($currentPassword) || empty($newPassword)) {
            throw new InvalidArgumentException('当前密码和新密码不能为空');
        }

        // 验证新密码强度
        if (strlen($newPassword) < 6) {
            throw new InvalidArgumentException('新密码长度不能少于6位');
        }

        // 获取用户信息
        $userData = $this->getUserById($userId);

        if (! $userData) {
            throw new InvalidArgumentException('用户不存在');
        }

        // 验证当前密码
        if (! password_verify($currentPassword, $userData['password_hash'] ?? '')) {
            throw new InvalidArgumentException('当前密码错误');
        }

        try {
            $result = $this->userRepository->update($userId, [
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->logger->info('用户密码已更新', ['user_id' => $userId]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('更新用户密码失败: ' . $e->getMessage(), ['user_id' => $userId]);
            throw $e;
        }
    }

    /**
     * 获取用户列表.
     *
     * @param array<string, mixed> $params 查询参数
     * @return array<array<string, mixed>> 用户列表数据
     */
    public function getUsers(array $params = []): array
    {
        // 构建查询条件
        $conditions = [];

        // 搜索条件
        if (isset($params['keyword']) && $params['keyword']) {
            $keyword = $params['keyword'];
            $conditions['OR'] = [
                ['email', 'LIKE', '%' . $keyword . '%'],
                ['real_name', 'LIKE', '%' . $keyword . '%'],
            ];
        }

        // 活跃状态筛选
        if (isset($params['is_active'])) {
            $conditions['is_active'] = $params['is_active'];
        }

        // 管理员状态筛选
        if (isset($params['is_admin'])) {
            $conditions['is_admin'] = $params['is_admin'];
        }

        // 排序
        $sortBy = $params['sort_by'] ?? 'created_at';
        $sortOrder = $params['sort_order'] ?? 'desc';

        try {
            // 使用Repository获取用户列表（按照实际方法参数签名调用）
            $userCollection = $this->userRepository->findAllBy(
                $conditions,
                ['*'], // 查询所有字段
                [$sortBy => $sortOrder]
            );
            
            // 将Collection转换为数组格式
            $result = [];
            foreach ($userCollection as $user) {
                $result[] = $this->userToArray($user);
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error('获取用户列表失败: ' . $e->getMessage(), ['params' => $params]);
            throw $e;
        }
    }

    /**
     * 检查用户是否为管理员.
     *
     * @param array<string, mixed> $userData 用户数据数组
     * @return bool 是否为管理员
     */
    public function isAdmin(array $userData): bool
    {
        return (bool)($userData['is_admin'] ?? false);
    }

    /**
     * 切换用户活跃状态
     *
     * @param int $id 用户ID
     * @return bool 切换是否成功
     * @throws InvalidArgumentException
     */
    public function toggleUserActiveStatus(int $id): bool
    {
        $userData = $this->getUserById($id);

        if (! $userData) {
            throw new InvalidArgumentException('用户不存在');
        }

        $newActiveStatus = !($userData['is_active'] ?? false);

        try {
            return $this->userRepository->update($id, [
                'is_active' => $newActiveStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            $this->logger->error('切换用户活跃状态失败: ' . $e->getMessage(), ['user_id' => $id]);
            throw $e;
        }
    }

    /**
     * 验证用户数据.
     *
     * @param array<string, mixed> $data 用户数据
     * @throws InvalidArgumentException
     */
    protected function validateUserData(array $data): void
    {
        // 检查必填字段
        $requiredFields = ['email', 'password'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException(ucfirst($field) . '不能为空');
            }
        }

        // 验证邮箱格式
        if (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('邮箱格式不正确');
        }

        // 验证密码长度
        if (strlen($data['password']) < 6) {
            throw new InvalidArgumentException('密码长度不能少于6位');
        }
    }

    /**
     * 将User模型对象转换为数组
     * 
     * @param User $user User模型对象
     * @return array<string, mixed> 用户信息数组
     */
    protected function userToArray(User $user): array
    {
        // 获取所有可访问的属性
        return [
            'id' => $user->id ?? null,
            'email' => $user->email ?? null,
            'real_name' => $user->real_name ?? null,
            'avatar' => $user->avatar ?? null,
            'bio' => $user->bio ?? null,
            'is_active' => $user->is_active ?? null,
            'is_admin' => $user->is_admin ?? null,
            'created_at' => $user->created_at ?? null,
            'updated_at' => $user->updated_at ?? null
        ];
    }
}
