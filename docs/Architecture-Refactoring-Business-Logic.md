# 技术架构重构文档 - 业务逻辑层优化

## 3.2 业务逻辑层优化

### 3.2.1 重构UserService

重构了`App\Service\UserService`类，移除直接的User模型依赖，全面使用UserRepository：

- 引入`UserRepository`并通过依赖注入获取实例
- 移除所有直接使用`User`模型的代码，替换为Repository方法调用
- 修复密码字段处理问题，统一使用`password_hash`字段
- 增加异常处理和日志记录，提高代码健壮性
- 修改方法签名，确保返回一致格式的数据

### 3.2.2 重构PermissionService

重构了`App\Service\PermissionService`类，主要改进包括：

- 将权限相关功能从SystemService迁移到PermissionService
- 引入UserRepository替代直接使用User模型
- 优化角色更新逻辑，增加变更日志记录
- 确保返回数据格式统一

### 3.2.3 代码示例

用户服务中的登录方法优化示例：

```php
/**
 * 用户登录认证
 *
 * @param string $email 邮箱
 * @param string $password 密码
 * @return array 包含用户信息和token的数组
 * @throws \InvalidArgumentException 当邮箱或密码不正确时抛出
 */
public function login(string $email, string $password): array
{
    // 查找用户
    $user = $this->userRepository->findBy(['email' => $email]);
    
    // 验证用户是否存在
    if (!$user) {
        $this->logger->warning('用户登录失败：用户不存在', ['email' => $email]);
        throw new \InvalidArgumentException('邮箱或密码错误');
    }
    
    // 验证密码
    $storedPasswordHash = $user['password_hash'] ?? null;
    if (!$storedPasswordHash || !password_verify($password, $storedPasswordHash)) {
        $this->logger->warning('用户登录失败：密码错误', ['email' => $email]);
        throw new \InvalidArgumentException('邮箱或密码错误');
    }
    
    // 验证用户状态
    if ($user['status'] !== 1) {
        $this->logger->warning('用户登录失败：用户账号未激活', ['email' => $email, 'status' => $user['status']]);
        throw new \InvalidArgumentException('账号未激活或已被禁用');
    }
    
    $this->logger->info('用户登录成功', ['user_id' => $user['id'], 'email' => $email]);
    return $user;
}
```

用户服务中的密码修改方法优化示例：

```php
/**
 * 修改用户密码
 *
 * @param int $userId 用户ID
 * @param string $currentPassword 当前密码
 * @param string $newPassword 新密码
 * @return bool 修改是否成功
 * @throws \InvalidArgumentException 当参数验证失败时抛出
 */
public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
{
    // 参数验证
    if (empty($currentPassword) || empty($newPassword)) {
        throw new \InvalidArgumentException('当前密码和新密码不能为空');
    }
    
    if (strlen($newPassword) < 6) {
        throw new \InvalidArgumentException('新密码长度不能小于6位');
    }
    
    // 获取用户信息
    $user = $this->userRepository->findById($userId);
    if (!$user) {
        throw new \InvalidArgumentException('用户不存在');
    }
    
    // 验证当前密码
    $storedPasswordHash = $user['password_hash'] ?? null;
    if (!$storedPasswordHash || !password_verify($currentPassword, $storedPasswordHash)) {
        $this->logger->warning('修改密码失败：当前密码错误', ['user_id' => $userId]);
        throw new \InvalidArgumentException('当前密码错误');
    }
    
    // 更新密码
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $result = $this->userRepository->update($userId, ['password_hash' => $passwordHash]);
    
    if ($result) {
        $this->logger->info('用户密码修改成功', ['user_id' => $userId]);
    } else {
        $this->logger->error('用户密码修改失败', ['user_id' => $userId]);
    }
    
    return $result;
}
```

### 3.2.4 服务层数据处理优化

服务层继续优化数据处理方式，保持与框架实践一致：

- 保持使用数组格式进行数据传递
- 确保数据处理逻辑的一致性和可维护性
- 继续优化方法签名，提高代码可读性
- 完善异常处理机制

[返回主索引](./Architecture-Refactoring-Main.md)