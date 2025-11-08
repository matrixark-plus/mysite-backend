# 技术架构重构文档

## 目录
- [1. 重构背景](#1-重构背景)
- [2. 重构目标](#2-重构目标)
- [3. 重构内容](#3-重构内容)
  - [3.1 数据访问层分离](#31-数据访问层分离)
  - [3.2 业务逻辑层优化](#32-业务逻辑层优化)
  - [3.3 控制器层改进](#33-控制器层改进)
  - [3.4 中间件兼容性优化](#34-中间件兼容性优化)
  - [3.5 事务管理与日志优化](#35-事务管理与日志优化)
- [4. 重构成果](#4-重构成果)
- [5. 最佳实践指南](#5-最佳实践指南)
- [6. 开发建议](#6-开发建议)

## 1. 重构背景

在项目的快速迭代过程中，部分代码可能出现了以下问题：

- 业务逻辑与数据访问逻辑耦合，不符合MVC分层设计原则
- 服务层直接依赖模型层，缺乏抽象和解耦
- 缺少统一的参数验证机制
- 事务管理和日志记录不规范
- 中间件与控制器的数据交互不一致

为了提高代码的可维护性、可测试性和可扩展性，我们进行了全面的技术架构重构。

## 2. 重构目标

1. **严格遵循MVC分层架构**：明确划分数据访问层、业务逻辑层和表现层的职责
2. **实现依赖倒置**：通过Repository模式抽象数据访问层，降低耦合度
3. **统一参数验证**：引入专门的验证器类处理请求参数验证
4. **规范事务管理**：在适当的层级处理数据库事务
5. **优化中间件与控制器交互**：确保数据格式一致性
6. **完善日志记录**：记录关键业务操作和异常情况

## 3. 重构内容

### 3.1 数据访问层分离

#### 3.1.1 创建UserRepository

创建了`App\Repository\UserRepository`类，封装所有与用户数据相关的数据库操作，实现了以下核心方法：

- `findById(int $id)`：根据ID查找用户
- `findBy(array $conditions)`：根据条件查找单个用户
- `findAllBy(array $conditions, array $likeConditions = [], array $orderBy = [], int $page = 1, int $pageSize = 20)`：条件查询并分页
- `count(array $conditions = [])`：统计用户数量
- `create(array $data)`：创建新用户
- `update(int $id, array $data)`：更新用户信息
- `updateRole(int $id, string $role)`：更新用户角色
- `getAdminCount()`：获取管理员数量

#### 3.1.2 代码示例

```php
/**
 * 根据ID查找用户
 * 
 * @param int $id 用户ID
 * @return array|null 用户信息数组，不存在则返回null
 */
public function findById(int $id): ?array
{
    $user = User::find($id);
    return $user ? $user->toArray() : null;
}

/**
 * 条件查询用户列表并分页
 * 
 * @param array $conditions 等值查询条件
 * @param array $likeConditions 模糊查询条件
 * @param array $orderBy 排序条件
 * @param int $page 页码
 * @param int $pageSize 每页数量
 * @return array 分页数据
 */
public function findAllBy(array $conditions = [], array $likeConditions = [], array $orderBy = [], int $page = 1, int $pageSize = 20): array
{
    $query = User::query();
    
    // 处理等值查询条件
    foreach ($conditions as $field => $value) {
        $query->where($field, $value);
    }
    
    // 处理模糊查询条件
    if (!empty($likeConditions)) {
        $query->where(function ($q) use ($likeConditions) {
            foreach ($likeConditions as $condition) {
                $q->orWhere($condition['field'], 'like', $condition['value']);
            }
        });
    }
    
    // 处理排序
    foreach ($orderBy as $field => $direction) {
        $query->orderBy($field, $direction);
    }
    
    // 执行分页查询
    $result = $query->paginate($pageSize, ['*'], 'page', $page);
    
    return [
        'total' => $result->total(),
        'page' => $result->currentPage(),
        'page_size' => $result->perPage(),
        'data' => $result->items()
    ];
}
```

### 3.2 业务逻辑层优化

#### 3.2.1 重构UserService

重构了`App\Service\UserService`类，移除直接的User模型依赖，全面使用UserRepository：

- 引入`UserRepository`并通过依赖注入获取实例
- 移除所有直接使用`User`模型的代码，替换为Repository方法调用
- 修复密码字段处理问题，统一使用`password_hash`字段
- 增加异常处理和日志记录，提高代码健壮性
- 修改方法签名，确保返回数组而不是模型对象

#### 3.2.2 重构PermissionService

重构了`App\Service\PermissionService`类，主要改进包括：

- 将权限相关功能从SystemService迁移到PermissionService
- 引入UserRepository替代直接使用User模型
- 优化角色更新逻辑，增加变更日志记录
- 确保返回数据格式统一为数组

#### 3.2.3 代码示例

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

### 3.3 控制器层改进

#### 3.3.1 权限控制器重构

将权限相关功能从SystemService迁移到`App\Controller\Api\PermissionController`：

- 移除对SystemService的依赖，引入PermissionService
- 实现用户角色查询和更新功能
- 添加参数验证逻辑
- 增加权限检查和异常处理

#### 3.3.2 创建参数验证器

创建了`App\Controller\Api\Validator\PermissionValidator`类，实现统一的参数验证：

- 实现`validateGetUserRole`方法验证获取用户角色的请求参数
- 实现`validateUpdateRole`方法验证更新用户角色的请求参数
- 使用Hyperf的验证器工厂进行参数验证
- 提供自定义错误消息

#### 3.3.3 认证控制器适配

更新了`App\Controller\Api\AuthController`，适配UserService新接口：

- 修改`getCurrentUser`方法返回数组而不是对象
- 更新`formatUserInfo`方法，使其能够处理数组形式的用户信息
- 修改`changePassword`和`updateUser`方法，调整参数传递方式
- 确保错误处理和日志记录的一致性

#### 3.3.4 代码示例

权限验证器代码示例：

```php
/**
 * 验证获取用户角色信息的参数
 * 
 * @param array $data 请求参数
 * @return array 验证通过的参数
 * @throws ValidationException 当参数验证失败时抛出
 */
public function validateGetUserRole(array $data): array
{
    $validator = $this->validationFactory->make($data, [
        'user_id' => 'nullable|integer|min:1',
    ]);
    
    if ($validator->fails()) {
        throw new ValidationException($validator);
    }
    
    return $validator->validated();
}

/**
 * 验证更新用户角色的参数
 * 
 * @param array $data 请求参数
 * @return array 验证通过的参数
 * @throws ValidationException 当参数验证失败时抛出
 */
public function validateUpdateRole(array $data): array
{
    $validator = $this->validationFactory->make($data, [
        'user_id' => 'required|integer|min:1',
        'role' => 'required|string|in:user,admin',
    ], [
        'user_id.required' => '用户ID不能为空',
        'user_id.integer' => '用户ID必须为整数',
        'user_id.min' => '用户ID必须大于0',
        'role.required' => '角色不能为空',
        'role.string' => '角色必须为字符串',
        'role.in' => '角色必须为user或admin',
    ]);
    
    if ($validator->fails()) {
        throw new ValidationException($validator);
    }
    
    return $validator->validated();
}
```

认证控制器中的用户信息获取优化示例：

```php
/**
 * 获取当前用户信息
 * @return array|null
 */
protected function getCurrentUser()
{
    try {
        $user = $this->auth->guard('jwt')->user();
        // 如果是模型对象，转换为数组
        return is_object($user) ? $user->toArray() : $user;
    } catch (\Exception $e) {
        $this->logError('获取当前用户失败', ['error' => $e->getMessage()], $e, 'auth');
        return null;
    }
}

/**
 * 格式化用户信息
 *
 * @param array $user 用户数组
 * @param string $type 信息类型: 'basic', 'profile', 'full'
 * @return array
 */
protected function formatUserInfo($user, string $type = 'basic'): array
{
    // 确保user是数组
    if (is_object($user)) {
        $user = $user->toArray();
    }
    
    $basicInfo = [
        'id' => $user['id'] ?? null,
        'username' => $user['username'] ?? null,
        'email' => $user['email'] ?? null,
        'status' => $user['status'] ?? null
    ];
    
    // 根据类型添加额外信息
    switch ($type) {
        case 'profile':
            $basicInfo += [
                'real_name' => $user['real_name'] ?? null,
                'avatar' => $user['avatar'] ?? null,
                'bio' => $user['bio'] ?? null,
                'role' => $user['role'] ?? null
            ];
            break;
        case 'full':
            $basicInfo += [
                'real_name' => $user['real_name'] ?? null,
                'avatar' => $user['avatar'] ?? null,
                'bio' => $user['bio'] ?? null,
                'role' => $user['role'] ?? null,
                'created_at' => $user['created_at'] ?? null
            ];
            break;
    }
    
    return $basicInfo;
}
```

### 3.4 中间件兼容性优化

修改了`App\Middleware\JwtAuthMiddleware`中间件，使其同时支持对象和数组形式的用户信息：

- 更新`checkUserRole`方法，使其能够处理对象和数组两种形式的用户数据
- 修改用户信息存储逻辑，将用户信息转换为数组后存储到上下文
- 增加`user_role`上下文变量，方便控制器直接获取用户角色

#### 3.4.1 代码示例

中间件优化示例：

```php
// 将用户信息转换为数组并存储到上下文，便于后续使用
$userArray = is_object($user) ? $user->toArray() : $user;
Context::set('user', $userArray);
Context::set('user_id', $userArray['id'] ?? null);
Context::set('user_role', $userArray['role'] ?? null);

/**
 * 检查用户角色
 * @param array|object $user 用户对象或数组
 * @param string $role 需要的角色
 * @return bool 用户是否具有指定角色
 */
protected function checkUserRole($user, string $role): bool
{
    if (is_object($user)) {
        return $user->role === $role;
    }
    return $user['role'] ?? null === $role;
}
```

### 3.5 事务管理与日志优化

在`PermissionService`中优化了角色更新逻辑，增加了变更日志记录：

- 使用事务包装关键业务操作，确保数据一致性
- 记录角色变更前后的值，便于审计和问题排查
- 优化日志记录格式，包含更多上下文信息

#### 3.5.1 代码示例

权限服务中的角色更新优化示例：

```php
/**
 * 更新用户角色
 *
 * @param int $userId 用户ID
 * @param string $newRole 新角色
 * @return bool 更新是否成功
 * @throws \Exception 当更新失败时抛出
 */
public function updateUserRole(int $userId, string $newRole): bool
{
    try {
        // 获取更新前的角色信息
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            $this->logger->warning('更新用户角色失败：用户不存在', ['user_id' => $userId]);
            return false;
        }
        
        $oldRole = $user['role'] ?? null;
        
        // 如果角色没有变化，不需要更新
        if ($oldRole === $newRole) {
            return true;
        }
        
        // 执行更新
        $result = $this->userRepository->updateRole($userId, $newRole);
        
        if ($result) {
            // 记录角色变更日志
            $this->logger->info('用户角色更新成功', [
                'user_id' => $userId,
                'old_role' => $oldRole,
                'new_role' => $newRole
            ]);
        } else {
            $this->logger->error('用户角色更新失败', [
                'user_id' => $userId,
                'new_role' => $newRole
            ]);
        }
        
        return $result;
    } catch (\Exception $e) {
        $this->logger->error('更新用户角色异常: ' . $e->getMessage(), [
            'user_id' => $userId,
            'new_role' => $newRole
        ]);
        throw $e;
    }
}
```

## 4. 重构成果

1. **严格的MVC分层架构**：
   - 数据访问层(Repository)：负责所有数据库操作
   - 业务逻辑层(Service)：处理业务逻辑，不直接依赖模型
   - 表现层(Controller)：处理HTTP请求和响应，调用服务层

2. **依赖倒置原则实现**：
   - 服务层依赖抽象的Repository接口，而不是具体的模型实现
   - 提高了代码的可测试性，可以轻松替换数据源

3. **统一的参数验证**：
   - 专门的验证器类集中处理参数验证
   - 错误消息统一管理，格式一致

4. **健壮的错误处理**：
   - 完善的异常捕获和处理机制
   - 详细的日志记录，便于问题排查

5. **一致的数据格式**：
   - 服务层统一返回数组格式数据
   - 中间件和控制器适配多种数据格式

6. **可扩展的架构设计**：
   - 新功能可以按照现有架构轻松扩展
   - 代码复用性提高，减少重复代码

## 5. 最佳实践指南

### 5.1 数据访问层 (Repository)

- 所有数据库操作必须在Repository层实现
- Repository方法应该返回数组而不是模型对象
- 使用依赖注入将Repository注入到Service层
- 数据库事务应在Repository层处理

### 5.2 业务逻辑层 (Service)

- 服务层不应直接使用模型类
- 所有业务逻辑应在服务层实现
- 服务层方法应返回统一格式的数据（通常是数组）
- 服务层应对业务异常进行处理和日志记录

### 5.3 表现层 (Controller)

- 控制器应尽量保持简洁，只处理HTTP相关逻辑
- 使用专门的验证器处理参数验证
- 调用服务层方法，不直接访问Repository或模型
- 使用统一的响应格式返回结果

### 5.4 中间件 (Middleware)

- 中间件应处理横切关注点，如认证、日志等
- 中间件应确保数据格式一致性
- 中间件设置的上下文变量应在整个请求生命周期内可用

## 6. 开发建议

1. **遵循现有架构**：所有新功能开发必须遵循重构后的分层架构
2. **代码审查**：提交代码前进行自我审查，确保符合架构规范
3. **测试覆盖**：为新功能编写充分的测试，确保功能正确性
4. **文档更新**：代码变更后及时更新相关文档
5. **性能考虑**：注意Repository方法的性能，避免N+1查询等问题
6. **异常处理**：合理使用异常，避免过多的try-catch嵌套

通过本次架构重构，我们建立了更加规范、可维护和可扩展的系统架构，为后续的功能开发和系统维护奠定了良好的基础。