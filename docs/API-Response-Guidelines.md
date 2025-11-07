# API响应规范文档

## 目录
- [1. 业务码与HTTP状态码一致性规范](#1-业务码与http状态码一致性规范)
- [2. 状态码常量定义](#2-状态码常量定义)
- [3. 响应消息常量定义](#3-响应消息常量定义)
- [4. 响应格式规范](#4-响应格式规范)
- [5. 响应工具使用指南](#5-响应工具使用指南)
- [6. 异常处理](#6-异常处理)
- [7. 开发建议](#7-开发建议)

## 业务码与HTTP状态码一致性规范

根据代码分析，本项目采用业务码与HTTP状态码保持一致的设计规范。这种设计有以下优势：

1. **简化状态处理**：前端可以直接通过HTTP状态码快速判断请求是否成功
2. **统一错误处理**：API响应中的`code`字段与HTTP状态码保持一致，避免状态混乱
3. **便于调试**：标准化的状态码使问题定位更加清晰

## 2. 状态码常量定义

### 2.1 状态码常量类

状态码常量定义在 `App\Constants\StatusCode` 类中，主要包含以下几类状态码：

1. **成功状态码**：如 200、201、204 等
2. **客户端错误状态码**：如 400、401、403、404 等
3. **服务器错误状态码**：如 500、502、503、504 等
4. **业务错误状态码**：如 10001、10002 等（保留扩展使用）

## 3. 响应消息常量定义

### 3.1 响应消息常量类

响应消息常量定义在 `App\Constants\ResponseMessage` 类中，用于统一管理所有API返回的消息内容。主要包含以下几类消息：

1. **成功消息**：如 '操作成功'、'创建成功' 等
2. **评论相关消息**：如 '评论创建成功，等待审核'、'评论审核通过' 等
3. **参数错误消息**：如 '参数错误'、'缺少必要参数' 等
4. **认证授权错误消息**：如 '未授权访问'、'令牌已过期' 等
5. **资源错误消息**：如 '资源不存在'、'资源已被删除' 等
6. **服务器错误消息**：如 '服务器内部错误'、'数据库操作失败' 等

### 3.2 获取默认消息方法

`ResponseMessage` 类提供 `getDefaultMessage` 方法，根据状态码获取对应的默认消息文本。

```php
// 使用示例
$message = ResponseMessage::getDefaultMessage(StatusCode::SUCCESS); // 返回 '操作成功'
```

## 4. 响应格式规范

### 4.1 响应格式结构

所有API响应均采用JSON格式，包含以下字段：

```json
{
  "code": 200,      // 状态码（与HTTP状态码一致）
  "message": "",   // 响应消息（使用ResponseMessage常量）
  "data": null     // 响应数据
}

### 4.2 成功响应

```json
{
  "code": 200,
  "message": "操作成功",
  "data": { ... }
}
```

### 错误响应

```json
{
  "code": 400,
  "message": "请求参数错误",
  "data": null
}
```

### 分页响应

```json
{
  "code": 200,
  "message": "",
  "data": {
    "items": [ ... ],
    "meta": {
      "total": 100,
      "page": 1,
      "pageSize": 10,
      "totalPages": 10
    }
  }
}
```

## 5. 响应工具使用指南

### 5.1 在控制器中使用响应方法

所有控制器继承自`AbstractController`，该类通过`ResponseTrait`提供了以下响应方法：

#### 5.1.1 成功响应 (success)

```php
// 基础使用
return $this->success($data); // 默认code=200，message使用默认值

// 完整参数使用
return $this->success(
    $data, 
    ResponseMessage::COMMENT_CREATE_SUCCESS, 
    StatusCode::SUCCESS
);
```

#### 5.1.2 失败响应 (fail)

```php
// 基础使用
return $this->fail(StatusCode::BAD_REQUEST); // 默认使用对应code的消息

// 完整参数使用
return $this->fail(
    StatusCode::BAD_REQUEST, 
    ResponseMessage::COMMENT_PARAM_REQUIRED
);

// 带错误信息的使用
return $this->fail(
    StatusCode::INTERNAL_SERVER_ERROR, 
    ResponseMessage::COMMENT_CREATE_FAILED . ': ' . $e->getMessage()
);
```

#### 5.1.3 分页响应 (paginate)

```php
return $this->paginate(
    $items, // 数据列表
    $meta,  // 分页元数据
    ResponseMessage::COMMENT_LIST_SUCCESS
);
```

### 5.2 方法参数说明

#### success 方法
- `$data`: 响应数据，默认为空数组
- `$message`: 响应消息，必须使用`ResponseMessage`类中定义的常量
- `$code`: 状态码，必须使用`StatusCode`类中定义的常量，默认为200

#### fail 方法
- `$code`: 错误状态码，必须使用`StatusCode`类中定义的常量
- `$message`: 错误消息，必须使用`ResponseMessage`类中定义的常量
- `$data`: 附加数据，默认为null

#### paginate 方法
- `$data`: 分页数据列表
- `$meta`: 分页元数据（total, page, pageSize等）
- `$message`: 响应消息，必须使用`ResponseMessage`类中定义的常量
- `$code`: 状态码，必须使用`StatusCode`类中定义的常量，默认为200

## 6. 异常处理

异常处理类`AppExceptionHandler`会自动捕获异常并返回统一格式的错误响应：

- 业务码（code）与HTTP状态码保持一致
- 如果异常设置了code值，会优先使用该值
- 错误消息会根据环境自动调整（开发环境显示详细错误，生产环境显示通用错误）

## 7. 开发建议

1. **始终使用常量**：使用`StatusCode`类中定义的状态码常量，使用`ResponseMessage`类中定义的消息常量，避免硬编码数字和字符串
2. **保持一致性**：确保所有API端点遵循相同的响应格式
3. **详细错误信息**：在开发环境提供详细的错误信息，在生产环境提供安全的通用信息
4. **适当的状态码**：为不同类型的操作选择合适的HTTP状态码
5. **扩展消息常量**：当需要新的响应消息时，应首先在`ResponseMessage`类中添加新的常量，然后在代码中引用
6. **遵循RESTful原则**：状态码的使用应符合RESTful API设计规范

## 示例

### 成功响应示例
```php
// 创建资源成功
return $this->success(['id' => 1, 'name' => '示例'], '创建成功', StatusCode::CREATED);
```

### 错误响应示例
```php
// 参数验证失败
return $this->fail(StatusCode::UNPROCESSABLE_ENTITY, '邮箱格式不正确');
```

### 分页响应示例
```php
// 获取分页数据
$items = [/* 数据列表 */];
$meta = [
    'total' => 100,
    'page' => 1,
    'pageSize' => 10,
    'totalPages' => 10
];
return $this->paginate($items, $meta, '获取成功');
```