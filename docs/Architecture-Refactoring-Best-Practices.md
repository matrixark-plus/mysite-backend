# 技术架构重构文档 - 最佳实践指南

## 4 最佳实践指南

### 4.1 数据访问层(Repository)最佳实践

1. **单一职责原则**：每个Repository只负责一个实体或聚合根的数据访问
2. **返回数据格式统一**：所有Repository方法应返回数组或标准格式的数据
3. **事务管理**：数据库事务应在Repository层处理
4. **参数验证**：对输入参数进行验证，确保数据合法性
5. **异常处理**：捕获并记录数据库操作异常
6. **查询优化**：避免N+1查询问题，合理使用索引
7. **代码注释**：提供详细的方法文档注释

```php
/**
 * 事务处理示例
 *
 * @param array $data 事务数据
 * @return bool
 */
public function transaction(array $data): bool
{
    return Db::transaction(function () use ($data) {
        // 执行多条数据库操作
        $this->create($data['user']);
        $this->createProfile($data['profile']);
        $this->createSettings($data['settings']);
        return true;
    });
}
```

### 4.2 业务逻辑层(Service)最佳实践

1. **依赖注入**：通过构造函数注入所需的Repository和其他服务
2. **业务验证**：在服务层实现完整的业务规则验证
3. **异常处理**：使用业务异常表示业务错误，便于上层处理
4. **日志记录**：记录关键操作日志，包含足够的上下文信息
5. **数据转换**：使用Entity类在层间传递数据，提高类型安全性
6. **方法设计**：方法应遵循单一职责原则，参数不宜过多
7. **代码组织**：相关业务逻辑应放在同一个服务中

```php
/**
 * 服务层方法示例
 *
 * @param int $userId
 * @param array $orderData
 * @return OrderEntity
 * @throws BusinessException
 */
public function createOrder(int $userId, array $orderData): OrderEntity
{
    // 参数验证
    if (empty($orderData['items'])) {
        throw new BusinessException('订单至少包含一个商品', 'EMPTY_ORDER_ITEMS');
    }
    
    // 获取用户信息
    $user = $this->userRepository->findById($userId);
    if (!$user) {
        throw new BusinessException('用户不存在', 'USER_NOT_FOUND', 404);
    }
    
    // 业务逻辑处理
    $order = $this->processOrder($user, $orderData);
    
    // 保存订单
    $orderId = $this->orderRepository->create($order);
    
    // 记录日志
    $this->logger->info('订单创建成功', ['order_id' => $orderId, 'user_id' => $userId]);
    
    // 返回订单对象
    $orderEntity = OrderEntity::fromArray(['id' => $orderId] + $order);
    return $orderEntity;
}
```

### 4.3 表现层(Controller)最佳实践

1. **保持简洁**：控制器只处理HTTP请求和响应，不包含业务逻辑
2. **参数验证**：使用专门的验证器验证请求参数
3. **异常处理**：捕获服务层抛出的异常，返回友好的错误提示
4. **统一返回格式**：使用固定的结构返回数据和错误信息
5. **HTTP状态码**：使用合适的HTTP状态码表示不同的响应结果
6. **路由规范**：遵循RESTful API设计规范
7. **日志记录**：记录请求参数和处理结果

```php
/**
 * 控制器方法示例
 *
 * @param Request $request
 * @return array
 */
public function store(Request $request): array
{
    try {
        // 参数验证
        $validated = $this->validatorFactory->make(
            $request->all(),
            [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users',
                // 更多验证规则...
            ]
        )->validated();
        
        // 调用服务层
        $user = $this->userService->createUser($validated);
        
        // 返回成功响应
        return $this->success($user, '用户创建成功');
    } catch (\Hyperf\Validation\ValidationException $e) {
        // 参数验证失败
        return $this->fail($e->validator->first(), 400, 'VALIDATION_ERROR');
    } catch (BusinessException $e) {
        // 业务异常
        return $this->fail($e->getMessage(), $e->statusCode, $e->getErrorCode());
    } catch (\Exception $e) {
        // 系统异常
        $this->logger->error('创建用户失败', ['error' => $e]);
        return $this->fail('系统繁忙，请稍后重试', 500, 'SYSTEM_ERROR');
    }
}
```

### 4.4 中间件(Middleware)最佳实践

1. **关注点分离**：中间件只处理横切关注点，如认证、日志等
2. **执行顺序**：注意中间件的注册顺序，确保正确的执行流程
3. **错误处理**：在中间件中捕获并处理特定类型的异常
4. **上下文传递**：使用Context在中间件和控制器之间传递数据
5. **性能考虑**：避免在中间件中执行耗时操作
6. **可配置性**：提供灵活的配置选项，适应不同的需求
7. **日志记录**：记录中间件的关键操作，便于调试和监控

```php
/**
 * 中间件实现示例
 */
public function process(RequestInterface $request, \Closure $handler)
{
    $startTime = microtime(true);
    
    try {
        // 前置处理
        $this->logger->info('请求开始', [
            'path' => $request->path(),
            'method' => $request->getMethod(),
        ]);
        
        // 设置上下文信息
        Context::set('request_id', uniqid());
        
        // 继续处理请求
        $response = $handler($request);
        
        // 后置处理
        $executionTime = microtime(true) - $startTime;
        $this->logger->info('请求结束', [
            'request_id' => Context::get('request_id'),
            'execution_time' => $executionTime,
            'status_code' => $response->statusCode(),
        ]);
        
        return $response;
    } catch (\Exception $e) {
        // 异常处理
        $this->logger->error('请求异常', [
            'error' => $e->getMessage(),
            'request_id' => Context::get('request_id'),
        ]);
        
        throw $e;
    }
}
```

### 4.5 错误处理与日志记录最佳实践

1. **分层错误处理**：在不同层次捕获和处理适合的异常
2. **详细日志记录**：记录异常的完整信息，包括错误消息、堆栈跟踪和上下文
3. **敏感信息保护**：避免在日志中记录密码等敏感信息
4. **日志级别**：使用适当的日志级别（DEBUG、INFO、WARNING、ERROR）
5. **异常转换**：在适当的地方将系统异常转换为业务异常
6. **统一错误响应**：提供一致的错误响应格式
7. **错误码系统**：建立规范的错误码体系，便于问题定位

```php
/**
 * 错误处理示例
 */
try {
    // 业务操作
    $result = $this->performBusinessOperation();
} catch (PDOException $e) {
    // 数据库异常
    $this->logger->error('数据库操作失败', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'query' => $e->queryString ?? '',
    ]);
    throw new BusinessException('数据库操作失败', 'DATABASE_ERROR', 500, $e);
} catch (\Exception $e) {
    // 其他异常
    $this->logger->error('操作失败', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    throw $e;
}
```

### 4.6 性能优化建议

1. **数据库优化**：合理设计索引，避免全表扫描，使用分页查询
2. **缓存使用**：对热点数据使用Redis缓存，设置合理的过期时间
3. **异步处理**：耗时操作使用Job异步处理
4. **代码优化**：避免不必要的对象创建，减少函数调用层级
5. **资源管理**：及时释放不再需要的资源
6. **连接池**：使用连接池管理数据库连接
7. **请求合并**：批量处理相似请求，减少网络开销

### 4.7 安全性最佳实践

1. **输入验证**：所有用户输入必须进行验证
2. **SQL注入防护**：使用参数化查询或ORM框架
3. **XSS防护**：对用户输入进行HTML转义
4. **CSRF防护**：实现CSRF令牌验证
5. **密码安全**：使用bcrypt等安全算法加密存储密码
6. **权限控制**：实现细粒度的权限控制
7. **敏感信息保护**：不在日志中记录敏感信息，使用HTTPS传输数据

[返回主索引](./Architecture-Refactoring-Main.md)