# 技术架构重构文档 - 异常处理与统一返回格式

## 3.4 异常处理机制重构

### 3.4.1 创建业务异常类

创建了`App\Exception\BusinessException`业务异常类，用于表示可预测的业务错误情况。该类继承自`\Exception`，提供了以下功能：

- 自定义错误码和错误信息
- 支持HTTP状态码设置
- 便于在业务逻辑中抛出有意义的异常

### 3.4.2 异常处理优化

重构了全局异常处理器，主要改进包括：

- 区分业务异常和系统异常，提供不同的处理逻辑
- 为业务异常返回友好的错误提示和对应的错误码
- 为系统异常记录详细日志，返回通用错误信息
- 统一错误响应格式，包含错误码、错误信息等字段

### 3.4.3 代码示例

业务异常类实现：

```php
<?php

namespace App\Exception;

class BusinessException extends \Exception
{
    /**
     * @var int HTTP状态码
     */
    protected $statusCode = 400;
    
    /**
     * @var string 错误码
     */
    protected $errorCode = 'BUSINESS_ERROR';
    
    /**
     * BusinessException constructor.
     * @param string $message 错误信息
     * @param string $errorCode 错误码
     * @param int $statusCode HTTP状态码
     * @param \Throwable|null $previous 前一个异常
     */
    public function __construct(
        string $message = "业务异常", 
        string $errorCode = 'BUSINESS_ERROR', 
        int $statusCode = 400, 
        \Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this-\u003eerrorCode = $errorCode;
        $this-\u003estatusCode = $statusCode;
    }
    
    /**
     * 获取HTTP状态码
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this-\u003estatusCode;
    }
    
    /**
     * 获取错误码
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this-\u003eerrorCode;
    }
}
```

服务层中使用业务异常的示例：

```php
/**
 * 验证用户权限
 *
 * @param int $userId 用户ID
 * @param string $permission 需要验证的权限
 * @return bool
 * @throws BusinessException 当用户没有权限时抛出
 */
public function checkPermission(int $userId, string $permission): bool
{
    $user = $this-\u003euserRepository-\u003efindById($userId);
    
    if (!$user) {
        throw new BusinessException('用户不存在', 'USER_NOT_FOUND', 404);
    }
    
    // 检查用户权限
    if (!$this-\u003ehasPermission($user, $permission)) {
        throw new BusinessException(
            sprintf('用户没有「%s」权限', $permission),
            'PERMISSION_DENIED',
            403
        );
    }
    
    return true;
}
```

## 3.5 统一返回格式

### 3.5.1 定义统一响应结构

定义了统一的API响应格式，包括以下字段：

- `success`：布尔值，表示请求是否成功
- `data`：请求成功时返回的数据
- `message`：响应消息，成功时为"success"，失败时为错误信息
- `code`：响应码，成功时为0，失败时为对应的错误码
- `error_code`：错误码，仅在失败时返回

### 3.5.2 创建响应工具类

创建了响应工具类，提供了以下静态方法：

- `success(array $data = [], string $message = 'success')`：返回成功响应
- `fail(string $message = '操作失败', int $code = 1, string $errorCode = 'SYSTEM_ERROR')`：返回失败响应

### 3.5.3 代码示例

响应工具类实现：

```php
<?php

namespace App\Utils;

class Response
{
    /**
     * 返回成功响应
     *
     * @param array $data 返回的数据
     * @param string $message 响应消息
     * @return array
     */
    public static function success(array $data = [], string $message = 'success'): array
    {
        return [
            'success' =\u003e true,
            'data' =\u003e $data,
            'message' =\u003e $message,
            'code' =\u003e 0,
        ];
    }
    
    /**
     * 返回失败响应
     *
     * @param string $message 错误消息
     * @param int $code 错误码
     * @param string $errorCode 业务错误码
     * @return array
     */
    public static function fail(string $message = '操作失败', int $code = 1, string $errorCode = 'SYSTEM_ERROR'): array
    {
        return [
            'success' =\u003e false,
            'data' =\u003e [],
            'message' =\u003e $message,
            'code' =\u003e $code,
            'error_code' =\u003e $errorCode,
        ];
    }
}
```

控制器中使用统一返回格式的示例：

```php
/**
 * 获取用户信息
 *
 * @param int $id 用户ID
 * @return array
 */
public function getUserInfo(int $id): array
{
    try {
        $user = $this-\u003euserService-\u003egetUserInfo($id);
        
        if (!$user) {
            return $this-\u003efail('用户不存在', 404, 'USER_NOT_FOUND');
        }
        
        return $this-\u003esuccess($user);
    } catch (BusinessException $e) {
        return $this-\u003efail($e-\u003egetMessage(), $e-\u003estatusCode, $e-\u003egetErrorCode());
    } catch (\Exception $e) {
        $this-\u003elogger-\u003eerror('获取用户信息失败：' . $e-\u003egetMessage(), ['error' =\u003e $e]);
        return $this-\u003efail('获取用户信息失败');
    }
}
```

### 3.5.4 异常处理与统一返回格式的优势

1. **用户体验**：提供友好的错误提示
2. **开发效率**：统一的错误处理和返回格式减少重复代码
3. **调试效率**：详细的错误日志便于问题定位
4. **安全性**：不向客户端暴露系统内部错误详情
5. **前后端分离**：标准化的响应格式便于前端处理
6. **可维护性**：统一的异常处理机制易于维护和扩展

[返回主索引](./Architecture-Refactoring-Main.md)