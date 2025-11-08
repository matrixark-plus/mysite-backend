<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 用户服务
 * 封装用户相关的业务逻辑
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
     * 创建新用户
     *
     * @param array $data 用户数据
     * @return User
     * @throws \InvalidArgumentException
     */
    public function createUser(array $data): array
    {
        // 验证数据
        $this->validateUserData($data);
        
        // 检查用户名是否已存在
        if ($this->getUserByUsername($data['username'])) {
            throw new \InvalidArgumentException('用户名已存在');
        }
        
        // 检查邮箱是否已存在
        if ($this->getUserByEmail($data['email'])) {
            throw new \InvalidArgumentException('邮箱已被注册');
        }
        
        // 准备数据
        $userData = [
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'status' => $data['status'] ?? 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
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
            $user = $this->userRepository->create($userData);
            return $user;
        } catch (\Exception $e) {
            $this->logger->error('创建用户失败: ' . $e->getMessage(), ['data' => $data]);
            throw $e;
        }
    }
    
    /**
     * 根据ID获取用户
     *
     * @param int $id 用户ID
     * @return User|null
     */
    public function getUserById(int $id): ?array
    {
        return $this->userRepository->findById($id);
    }
    
    /**
     * 根据邮箱获取用户
     *
     * @param string $email 邮箱
     * @return User|null
     */
    public function getUserByEmail(string $email): ?array
    {
        return $this->userRepository->findBy(['email' => $email]);
    }
    
    /**
     * 根据用户名获取用户
     *
     * @param string $username 用户名
     * @return User|null
     */
    public function getUserByUsername(string $username): ?array
    {
        return $this->userRepository->findBy(['username' => $username]);
    }
    
    /**
     * 验证用户凭证
     *
     * @param string $email 邮箱
     * @param string $password 密码
     * @return User|null
     */
    public function validateCredentials(string $email, string $password): ?array
    {
        $user = $this->getUserByEmail($email);
        
        if (!$user) {
            return null;
        }
        
        // 检查密码是否正确
        if (!password_verify($password, $user['password_hash'] ?? '')) {
            return null;
        }
        
        // 检查用户状态
        if ($user['status'] !== 1) {
            return null;
        }
        
        return $user;
    }
    
    /**
     * 用户登录流程
     *
     * @param string $email 邮箱
     * @param string $password 密码
     * @return User
     * @throws \InvalidArgumentException
     */
    public function login(string $email, string $password): array
    {
        // 参数验证
        if (empty($email) || empty($password)) {
            throw new \InvalidArgumentException('邮箱和密码不能为空');
        }
        
        // 邮箱格式验证
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('邮箱格式不正确');
        }
        
        // 验证用户凭据
        $user = $this->validateCredentials($email, $password);
        
        if (!$user) {
            throw new \InvalidArgumentException('邮箱或密码错误');
        }
        
        return $user;
    }
    
    /**
     * 更新用户信息
     *
     * @param User $user 用户模型
     * @param array $data 更新数据
     * @return User
     * @throws \InvalidArgumentException
     */
    public function updateUser(int $id, array $data): array
    {
        // 检查用户是否存在
        $user = $this->getUserById($id);
        if (!$user) {
            throw new \InvalidArgumentException('用户不存在');
        }
        
        // 检查用户名是否被其他用户使用
        if (isset($data['username']) && $data['username'] !== $user['username']) {
            $existingUser = $this->userRepository->findBy(['username' => $data['username'], 'id' => ['!=' => $id]]);
            
            if ($existingUser) {
                throw new \InvalidArgumentException('用户名已被使用');
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
        
        // 确保更新时间戳
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        try {
            $result = $this->userRepository->update($id, $updateData);
            if ($result) {
                return $this->getUserById($id);
            }
            throw new \Exception('更新用户失败');
        } catch (\Exception $e) {
            $this->logger->error('更新用户信息失败: ' . $e->getMessage(), ['id' => $id, 'data' => $data]);
            throw $e;
        }
    }
    
    /**
     * 修改用户密码
     *
     * @param User $user 用户模型
     * @param string $currentPassword 当前密码
     * @param string $newPassword 新密码
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        // 参数验证
        if (empty($currentPassword) || empty($newPassword)) {
            throw new \InvalidArgumentException('当前密码和新密码不能为空');
        }
        
        // 验证新密码强度
        if (strlen($newPassword) < 6) {
            throw new \InvalidArgumentException('新密码长度不能少于6位');
        }
        
        // 获取用户信息
        $user = $this->getUserById($userId);
        
        if (!$user) {
            throw new \InvalidArgumentException('用户不存在');
        }
        
        // 验证当前密码
        if (!password_verify($currentPassword, $user['password_hash'] ?? '')) {
            throw new \InvalidArgumentException('当前密码错误');
        }
        
        try {
            $result = $this->userRepository->update($userId, [
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->logger->info('用户密码已更新', ['user_id' => $userId]);
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('更新用户密码失败: ' . $e->getMessage(), ['user_id' => $userId]);
            throw $e;
        }
    }
    
    /**
     * 获取用户列表
     *
     * @param array $params 查询参数
     * @return array
     */
    public function getUsers(array $params = []): array
    {
        // 构建查询条件
        $conditions = [];
        $likeConditions = [];
        
        // 搜索条件
        if (isset($params['keyword']) && $params['keyword']) {
            $keyword = $params['keyword'];
            $likeConditions = [
                ['field' => 'username', 'value' => '%' . $keyword . '%'],
                ['field' => 'email', 'value' => '%' . $keyword . '%'],
                ['field' => 'real_name', 'value' => '%' . $keyword . '%']
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
        
        // 分页
        $page = $params['page'] ?? 1;
        $pageSize = $params['page_size'] ?? 20;
        
        try {
            // 使用Repository获取用户列表
            $result = $this->userRepository->findAllBy(
                $conditions,
                $likeConditions,
                [$sortBy => $sortOrder],
                $page,
                $pageSize
            );
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('获取用户列表失败: ' . $e->getMessage(), ['params' => $params]);
            throw $e;
        }
    }
    
    /**
     * 验证用户数据
     *
     * @param array $data 用户数据
     * @throws \InvalidArgumentException
     */
    protected function validateUserData(array $data): void
    {
        if (empty($data['username'])) {
            throw new \InvalidArgumentException('用户名不能为空');
        }
        
        if (empty($data['email'])) {
            throw new \InvalidArgumentException('邮箱不能为空');
        }
        
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('邮箱格式不正确');
        }
        
        if (empty($data['password'])) {
            throw new \InvalidArgumentException('密码不能为空');
        }
        
        if (strlen($data['password']) < 6) {
            throw new \InvalidArgumentException('密码长度至少为6位');
        }
        
        // 验证用户名格式
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $data['username'])) {
            throw new \InvalidArgumentException('用户名格式不正确，只能包含字母、数字和下划线，长度3-20位');
        }
        
        // 如果有确认密码字段，验证两次密码是否一致
        if (isset($data['confirm_password']) && $data['password'] !== $data['confirm_password']) {
            throw new \InvalidArgumentException('两次输入的密码不一致');
        }
    }
    
    /**
     * 检查用户是否为管理员
     *
     * @param User $user 用户模型
     * @return bool
     */
    public function isAdmin(array $user): bool
    {
        return $user['role'] === 'admin';
    }
    
    /**
     * 切换用户状态
     *
     * @param int $id 用户ID
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function toggleUserStatus(int $id): bool
    {
        $user = $this->getUserById($id);
        
        if (!$user) {
            throw new \InvalidArgumentException('用户不存在');
        }
        
        $newStatus = $user['status'] === 1 ? 0 : 1;
        
        try {
            return $this->userRepository->update($id, [
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logger->error('切换用户状态失败: ' . $e->getMessage(), ['user_id' => $id]);
            throw $e;
        }
    }
}