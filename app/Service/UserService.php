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

        // 检查用户名是否已存在
        if ($this->getUserByUsername($data['username'])) {
            throw new InvalidArgumentException('用户名已存在');
        }

        // 检查邮箱是否已存在
        if ($this->getUserByEmail($data['email'])) {
            throw new InvalidArgumentException('邮箱已被注册');
        }

        // 准备数据
        $userData = [
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'status' => $data['status'] ?? 1,
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
        if (isset($data['role'])) {
            $userData['role'] = $data['role'];
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
    public function getUserByUsername(string $username): ?array
    {
        $user = $this->userRepository->findBy(['username' => $username]);
        return $user ? $this->userToArray($user) : null;
    }

    /**
     * 验证用户凭证
     *
     * @param string $email 邮箱
     * @param string $password 密码
     * @return array<string, mixed>|null 验证通过的用户信息数组或null
     */
    public function validateCredentials(string $email, string $password): ?array
    {
        $userData = $this->getUserByEmail($email);

        if (! $userData) {
            return null;
        }

        // 检查密码是否正确
        if (! password_verify($password, $userData['password_hash'] ?? '')) {
            return null;
        }

        // 检查用户状态
        if ($userData['status'] !== 1) {
            return null;
        }

        return $userData;
    }

    /**
     * 用户登录流程.
     *
     * @param string $email 邮箱
     * @param string $password 密码
     * @return array<string, mixed> 登录成功的用户信息数组
     * @throws InvalidArgumentException
     */
    public function login(string $email, string $password): array
    {
        // 参数验证
        if (empty($email) || empty($password)) {
            throw new InvalidArgumentException('邮箱和密码不能为空');
        }

        // 邮箱格式验证
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('邮箱格式不正确');
        }

        // 验证用户凭据
        $userData = $this->validateCredentials($email, $password);

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

        // 检查用户名是否被其他用户使用
        if (isset($data['username']) && $data['username'] !== $userData['username']) {
            $existingUser = $this->userRepository->findBy(['username' => $data['username'], 'id' => ['!=' => $id]]);

            if ($existingUser) {
                throw new InvalidArgumentException('用户名已被使用');
            }
        }

        // 准备更新数据
        $updateData = [];
        if (isset($data['username'])) {
            $updateData['username'] = $data['username'];
        }
        if (isset($data['real_name'])) {
            $updateData['real_name'] = $data['real_name'];
        }
        if (isset($data['avatar'])) {
            $updateData['avatar'] = $data['avatar'];
        }
        if (isset($data['bio'])) {
            $updateData['bio'] = $data['bio'];
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        if (isset($data['role'])) {
            $updateData['role'] = $data['role'];
        }

        // 如果没有需要更新的数据，直接返回原用户数据
        if (empty($updateData)) {
            return $userData;
        }

        // 确保更新时间戳
        $updateData['updated_at'] = date('Y-m-d H:i:s');

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
            // 将模糊查询条件整合到主条件中
            // 注意：这里假设Repository的findAllBy方法可以处理嵌套条件
            $conditions['OR'] = [
                ['username', 'LIKE', '%' . $keyword . '%'],
                ['email', 'LIKE', '%' . $keyword . '%'],
                ['real_name', 'LIKE', '%' . $keyword . '%'],
            ];
        }

        // 状态筛选
        if (isset($params['status'])) {
            $conditions['status'] = $params['status'];
        }

        // 角色筛选
        if (isset($params['role'])) {
            $conditions['role'] = $params['role'];
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
        return ($userData['role'] ?? '') === 'admin';
    }

    /**
     * 切换用户状态
     *
     * @param int $id 用户ID
     * @return bool 切换是否成功
     * @throws InvalidArgumentException
     */
    public function toggleUserStatus(int $id): bool
    {
        $userData = $this->getUserById($id);

        if (! $userData) {
            throw new InvalidArgumentException('用户不存在');
        }

        $newStatus = $userData['status'] === 1 ? 0 : 1;

        try {
            return $this->userRepository->update($id, [
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            $this->logger->error('切换用户状态失败: ' . $e->getMessage(), ['user_id' => $id]);
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
        $requiredFields = ['username', 'email', 'password'];
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
            'username' => $user->username ?? null,
            'email' => $user->email ?? null,
            'real_name' => $user->real_name ?? null,
            'avatar' => $user->avatar ?? null,
            'bio' => $user->bio ?? null,
            'role' => $user->role ?? null,
            'status' => $user->status ?? null,
            'created_at' => $user->created_at ?? null,
            'updated_at' => $user->updated_at ?? null
        ];
    }
}
