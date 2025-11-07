<?php

declare(strict_types=1);
/**
 * 状态码常量定义
 * 业务码与HTTP响应码保持一致
 */
namespace App\Constants;

class StatusCode
{
    // 成功相关状态码
    const SUCCESS = 200; // 成功
    const CREATED = 201; // 创建成功
    const NO_CONTENT = 204; // 无内容
    
    // 客户端错误相关状态码
    const BAD_REQUEST = 400; // 请求参数错误
    const UNAUTHORIZED = 401; // 未授权
    const FORBIDDEN = 403; // 禁止访问
    const NOT_FOUND = 404; // 资源不存在
    const METHOD_NOT_ALLOWED = 405; // 方法不允许
    const UNPROCESSABLE_ENTITY = 422; // 无法处理的实体
    
    // 服务器错误相关状态码
    const INTERNAL_SERVER_ERROR = 500; // 服务器内部错误
    const BAD_GATEWAY = 502; // 错误的网关
    const SERVICE_UNAVAILABLE = 503; // 服务不可用
    const GATEWAY_TIMEOUT = 504; // 网关超时
    
    // 业务错误相关状态码
    const VALIDATION_ERROR = 422; // 数据验证错误
    const BUSINESS_ERROR = 400; // 业务处理失败
    const DATA_DUPLICATE = 409; // 数据冲突/重复
    const DATA_EXISTS = 409; // 数据已存在
    
    // 注意：消息获取逻辑已移至ResponseMessage类
    // 请使用ResponseMessage::getDefaultMessage()获取默认消息
}