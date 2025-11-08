# 技术架构重构文档 - 表现层优化

## 3.3 表现层(Controller)优化

### 3.3.1 重构UserController

重构了`App\Controller\Api\UserController`类，主要改进包括：

- 移除直接的数据库操作，全面使用UserService
- 统一返回格式，使用固定的结构返回数据和错误信息
- 增加参数验证，使用专门的验证器
- 改进异常处理，提供友好的错误提示
- 移除业务逻辑，确保控制器只处理HTTP相关逻辑

### 3.3.2 重构AuthController

重构了`App\Controller\Api\AuthController`类，主要改进包括：

- 移除直接的数据库操作，使用UserService进行用户认证
- 添加了`formatUserInfo`方法用于过滤敏感字段
- 统一JWT token生成和返回格式
- 增强错误处理和日志记录

### 3.3.3 代码示例

用户控制器中获取用户列表的方法优化示例：

```php
/**
 * 获取用户列表
 *
 * @param Request $request
 * @return array
 * @throws \Hyperf\Validation\ValidationException
 */
public function getUsers(Request $request): array
{
    // 参数验证
    $validated = $this-\u003evalidatorFactory-\u003emake(
        $request-\u003eall(),
        [
            'page' =\u003e 'required|integer|min:1',
            'page_size' =\u003e 'required|integer|min:1|max:100',
            'username' =\u003e 'nullable|string',
            'email' =\u003e 'nullable|email',
        ],
        [],
        [
            'page' =\u003e '页码',
            'page_size' =\u003e '每页数量',
            'username' =\u003e '用户名',
            'email' =\u003e '邮箱',
        ]
    )-\u003evalidated();
    
    try {
        // 构建查询条件
        $conditions = [];
        $likeConditions = [];
        
        if (!empty($validated['username'])) {
            $likeConditions[] = ['field' =\u003e 'username', 'value' =\u003e '%' . $validated['username'] . '%'];
        }
        
        if (!empty($validated['email'])) {
            $likeConditions[] = ['field' =\u003e 'email', 'value' =\u003e '%' . $validated['email'] . '%'];
        }
        
        // 调用服务层获取数据
        $result = $this-\u003euserService-\u003egetUsers(
            $conditions,
            $likeConditions,
            ['id' =\u003e 'desc'],
            $validated['page'],
            $validated['page_size']
        );
        
        // 统一返回格式
        return $this-\u003esuccess($result);
    } catch (\Exception $e) {
        $this-\u003elogger-\u003eerror('获取用户列表失败：' . $e-\u003egetMessage(), ['error' =\u003e $e]);
        return $this-\u003efail('获取用户列表失败');
    }
}
```

认证控制器中登录方法优化示例：

```php
/**
 * 用户登录
 *
 * @param Request $request
 * @return array
 * @throws \Hyperf\Validation\ValidationException
 */
public function login(Request $request): array
{
    // 参数验证
    $validated = $this-\u003evalidatorFactory-\u003emake(
        $request-\u003eall(),
        [
            'email' =\u003e 'required|email',
            'password' =\u003e 'required|string',
        ],
        [],
        [
            'email' =\u003e '邮箱',
            'password' =\u003e '密码',
        ]
    )-\u003evalidated();
    
    try {
        // 调用服务层进行认证
        $user = $this-\u003euserService-\u003elogin($validated['email'], $validated['password']);
        
        // 生成JWT token
        $token = $this-\u003etokenManager-\u003egenToken($user['id']);
        
        // 使用方法获取安全的用户信息
        $userInfo = $this->formatUserInfo($user);
        
        /**
         * 格式化用户信息，过滤敏感字段
         * 
         * @param array $userData 用户数据
         * @return array 安全的用户信息
         */
        protected function formatUserInfo(array $userData): array
        {
            // 过滤敏感字段，只返回安全的用户信息
            $safeFields = ['id', 'username', 'email', 'avatar', 'status', 'created_at', 'updated_at'];
            $safeData = [];
            
            foreach ($safeFields as $field) {
                if (isset($userData[$field])) {
                    $safeData[$field] = $userData[$field];
                }
            }
            
            return $safeData;
        }
        
        // 统一返回格式
        return $this-\u003esuccess([
            'token' =\u003e $token,
            'user_info' =\u003e $userInfo,
        ]);
    } catch (\InvalidArgumentException $e) {
        // 处理业务异常，返回友好提示
        $this-\u003elogger-\u003ewarning('用户登录失败：' . $e-\u003egetMessage(), ['email' =\u003e $validated['email']]);
        return $this-\u003efail($e-\u003egetMessage(), 401);
    } catch (\Exception $e) {
        // 处理系统异常
        $this-\u003elogger-\u003eerror('用户登录异常：' . $e-\u003egetMessage(), ['error' =\u003e $e]);
        return $this-\u003efail('登录失败，请稍后重试');
    }
}
```

### 3.3.4 控制器层最佳实践

1. **保持简洁**：控制器应该只处理HTTP请求和响应，不包含业务逻辑
2. **参数验证**：使用专门的验证器进行参数验证
3. **异常处理**：区分业务异常和系统异常，提供不同的错误提示
4. **统一返回格式**：使用固定的结构返回数据和错误信息
5. **日志记录**：记录请求参数、处理结果和错误信息
6. **使用服务层**：所有业务操作都通过服务层进行，不直接访问Repository或模型

[返回主索引](./Architecture-Refactoring-Main.md)