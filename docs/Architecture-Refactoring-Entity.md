# 技术架构重构文档 - 用户数据传输优化

## 3.5 用户数据传输优化

### 3.5.1 数据传输优化

项目采用数组作为主要的数据传输格式，在Repository层、Service层和Controller层之间统一传递数据。这种方式具有以下优势：

- 符合PHP动态语言特性
- 减少不必要的对象创建和转换开销
- 简化代码，提高开发效率
- 保持与Hyperf框架典型实践一致

### 3.5.2 服务层数据处理

`UserService`直接处理数组格式的数据，实现简洁高效的数据处理：

```php
/**
 * 创建用户
 * 
 * @param array $requestData 用户创建请求数据
 * @return array 创建的用户信息
 */
public function createUser(array $requestData): array
{
    // 验证请求数据
    $this->validateUserData($requestData);
    
    // 检查用户名是否已存在
    if ($this->getUserByUsername($requestData['username'])) {
        throw new \InvalidArgumentException('用户名已存在');
    }
    
    // 检查邮箱是否已存在
    if ($this->getUserByEmail($requestData['email'])) {
        throw new \InvalidArgumentException('邮箱已存在');
    }
    
    // 准备用户数据
    $userData = $requestData;
    $userData['password_hash'] = $this->hashPassword($requestData['password']);
    unset($userData['password']);
    
    // 设置默认值
    $userData['status'] = $userData['status'] ?? 1;
    $userData['role'] = $userData['role'] ?? 'user';
    
    // 创建用户
    return $this->userRepository->create($userData);
}
```

### 3.5.3 控制器层数据处理

`AuthController`直接处理数组格式的数据，通过服务层进行业务逻辑处理：

```php
/**
 * 用户注册
 * @PostMapping(path="/register")
 * @return ResponseInterface
 */
public function register(): ResponseInterface
{
    try {
        // 获取请求参数
        $requestData = $this->request->all();
        $ip = $this->request->getServerParams()['remote_addr'] ?? 'unknown';
        
        // 记录注册请求
        $this->logAction('用户注册请求', [
            'email' => $requestData['email'] ?? '',
            'ip' => $ip,
        ]);
        
        // 调用服务创建用户
        $userData = $this->userService->createUser($requestData);
        
        // 自动登录并获取token
        $token = $this->auth->guard('jwt')->login([
            'id' => $userData['id'],
            'username' => $userData['username']
        ]);
        
        // 记录注册成功
        $this->logAction('用户注册成功', [
            'email' => $requestData['email'],
            'user_id' => $userData['id']
        ]);
        
        // 准备返回给客户端的用户信息（过滤敏感字段）
        $safeUserData = $this->formatUserInfo($userData);
        
        // 返回成功响应
        return $this->success([
            'token' => $token,
            'user' => $safeUserData,
        ], '注册成功');
    } catch (\InvalidArgumentException $e) {
        // 记录错误日志
        $this->logError('用户注册失败', [
            'error' => $e->getMessage()
        ]);
        
        return $this->fail($e->getMessage(), 400);
    } catch (\Exception $e) {
        // 记录系统错误
        $this->logError('用户注册系统错误', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return $this->fail('系统繁忙，请稍后再试', 500);
    }
}
```

### 3.5.4 数组数据传输的优势

使用数组作为数据传输格式具有以下优势：

1. **符合PHP特性**：充分利用PHP动态语言的灵活性
2. **性能优势**：减少对象创建和转换的开销
3. **简化代码**：避免不必要的类定义和方法调用
4. **框架一致性**：与Hyperf框架的典型实践保持一致
5. **易于调试**：数组数据在日志和调试过程中更加直观

### 3.5.5 数据验证处理

数据验证逻辑集中在服务层实现，通过专门的验证方法处理输入数据的合法性：

```php
/**
 * 验证用户数据
 * 
 * @param array $userData 用户数据
 * @throws \InvalidArgumentException
 */
protected function validateUserData(array $userData): void
{
    // 基本验证
    if (empty($userData['username'])) {
        throw new \InvalidArgumentException('用户名不能为空');
    }
    
    if (empty($userData['email'])) {
        throw new \InvalidArgumentException('邮箱不能为空');
    }
    
    if (empty($userData['password'])) {
        throw new \InvalidArgumentException('密码不能为空');
    }
    
    // 验证邮箱格式
    if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
        throw new \InvalidArgumentException('邮箱格式不正确');
    }
    
    // 验证密码长度
    if (strlen($userData['password']) < 6) {
        throw new \InvalidArgumentException('密码长度不能少于6个字符');
    }
    
    // 验证用户名格式
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $userData['username'])) {
        throw new \InvalidArgumentException('用户名格式不正确，只能包含字母、数字和下划线，长度3-20位');
    }
}
```

### 3.5.6 数组数据传输的最佳实践

在使用数组进行数据传输时，遵循以下最佳实践：

1. **一致性**：保持数组键名的一致性，使用camelCase或snake_case统一风格
2. **文档化**：为关键数据结构提供清晰的文档说明
3. **验证**：在服务层对输入数据进行严格验证
4. **过滤**：在返回给客户端前过滤敏感信息
5. **类型提示**：使用PHP的类型提示增强代码健壮性

[返回主索引](./Architecture-Refactoring-Main.md)