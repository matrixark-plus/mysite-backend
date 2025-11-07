<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\User;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Str;
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
     * 创建新用户
     *
     * @param array $data 用户数据
     * @return User
     * @throws \InvalidArgumentException
     */
    public function createUser(array $data): User
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
        
        // 创建用户
        $user = new User();
        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->password = $data['password']; // 利用setPasswordAttribute方法自动加密
        $user->status = $data['status'] ?? 1;
        $user->created_at = date('Y-m-d H:i:s');
        $user->updated_at = date('Y-m-d H:i:s');
        
        // 设置可选字段
        if (isset($data['real_name'])) {
            $user->real_name = $data['real_name'];
        }
        if (isset($data['avatar'])) {
            $user->avatar = $data['avatar'];
        }
        if (isset($data['bio'])) {
            $user->bio = $data['bio'];
        }
        if (isset($data['role'])) {
            $user->role = $data['role'];
        }
        
        Db::transaction(function () use ($user) {
            $user->save();
        });
        
        return $user;
    }
    
    /**
     * 根据ID获取用户
     *
     * @param int $id 用户ID
     * @return User|null
     */
    public function getUserById(int $id): ?User
    {
        return User::find($id);
    }
    
    /**
     * 根据邮箱获取用户
     *
     * @param string $email 邮箱
     * @return User|null
     */
    public function getUserByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }
    
    /**
     * 根据用户名获取用户
     *
     * @param string $username 用户名
     * @return User|null
     */
    public function getUserByUsername(string $username): ?User
    {
        return User::query()->where('username', $username)->first();
    }
    
    /**
     * 验证用户凭证
     *
     * @param string $email 邮箱
     * @param string $password 密码
     * @return User|null
     */
    public function validateCredentials(string $email, string $password): ?User
    {
        $user = $this->getUserByEmail($email);
        
        if (!$user) {
            return null;
        }
        
        // 检查密码是否正确
        if (!password_verify($password, $user->password_hash)) {
            return null;
        }
        
        // 检查用户状态
        if ($user->status !== 1) {
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
    public function login(string $email, string $password): User
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
    public function updateUser(User $user, array $data): User
    {
        // 检查用户名是否被其他用户使用
        if (isset($data['username']) && $data['username'] !== $user->username) {
            $existingUser = User::query()
                ->where('username', $data['username'])
                ->where('id', '!=', $user->id)
                ->first();
            
            if ($existingUser) {
                throw new \InvalidArgumentException('用户名已被使用');
            }
            
            $user->username = $data['username'];
        }
        
        // 更新其他字段
        if (isset($data['real_name'])) {
            $user->real_name = $data['real_name'];
        }
        if (isset($data['avatar'])) {
            $user->avatar = $data['avatar'];
        }
        if (isset($data['bio'])) {
            $user->bio = $data['bio'];
        }
        if (isset($data['status'])) {
            $user->status = $data['status'];
        }
        if (isset($data['role'])) {
            $user->role = $data['role'];
        }
        
        // 确保更新时间戳
        $user->updated_at = date('Y-m-d H:i:s');
        
        $user->save();
        return $user;
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
    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        // 参数验证
        if (empty($currentPassword) || empty($newPassword)) {
            throw new \InvalidArgumentException('当前密码和新密码不能为空');
        }
        
        // 验证当前密码
        if (!password_verify($currentPassword, $user->password_hash)) {
            throw new \InvalidArgumentException('当前密码错误');
        }
        
        // 验证新密码强度
        if (strlen($newPassword) < 6) {
            throw new \InvalidArgumentException('新密码长度不能少于6位');
        }
        
        // 设置新密码
        $user->password = $newPassword;
        $user->updated_at = date('Y-m-d H:i:s');
        return $user->save();
    }
    
    /**
     * 获取用户列表
     *
     * @param array $params 查询参数
     * @return array
     */
    public function getUsers(array $params = []): array
    {
        $query = User::query();
        
        // 搜索条件
        if (isset($params['keyword']) && $params['keyword']) {
            $keyword = $params['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', '%' . $keyword . '%')
                  ->orWhere('email', 'like', '%' . $keyword . '%')
                  ->orWhere('real_name', 'like', '%' . $keyword . '%');
            });
        }
        
        // 状态筛选
        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }
        
        // 角色筛选
        if (isset($params['role'])) {
            $query->where('role', $params['role']);
        }
        
        // 排序
        $sortBy = $params['sort_by'] ?? 'created_at';
        $sortOrder = $params['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        // 分页
        $page = $params['page'] ?? 1;
        $pageSize = $params['page_size'] ?? 20;
        
        $users = $query->paginate($pageSize, ['*'], 'page', $page);
        
        return [
            'total' => $users->total(),
            'page' => $users->currentPage(),
            'page_size' => $users->perPage(),
            'data' => $users->items()
        ];
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
    public function isAdmin(User $user): bool
    {
        return $user->role === 'admin';
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
        
        $user->status = $user->status === 1 ? 0 : 1;
        $user->updated_at = date('Y-m-d H:i:s');
        
        return $user->save();
    }
}