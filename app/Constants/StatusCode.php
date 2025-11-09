<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Constants;

class StatusCode
{
    // 成功相关状态码
    public const SUCCESS = 200; // 成功

    public const CREATED = 201; // 创建成功

    public const NO_CONTENT = 204; // 无内容

    // 客户端错误相关状态码
    public const BAD_REQUEST = 400; // 请求参数错误

    public const UNAUTHORIZED = 401; // 未授权

    public const FORBIDDEN = 403; // 禁止访问

    public const NOT_FOUND = 404; // 资源不存在

    public const METHOD_NOT_ALLOWED = 405; // 方法不允许

    public const UNPROCESSABLE_ENTITY = 422; // 无法处理的实体

    public const TOO_MANY_REQUESTS = 429; // 请求过于频繁

    // 服务器错误相关状态码
    public const INTERNAL_SERVER_ERROR = 500; // 服务器内部错误

    public const BAD_GATEWAY = 502; // 错误的网关

    public const SERVICE_UNAVAILABLE = 503; // 服务不可用

    public const GATEWAY_TIMEOUT = 504; // 网关超时

    // 业务错误相关状态码
    public const VALIDATION_ERROR = 422; // 数据验证错误

    public const BUSINESS_ERROR = 400; // 业务处理失败

    public const DATA_DUPLICATE = 409; // 数据冲突/重复

    public const DATA_EXISTS = 409; // 数据已存在

    // 注意：消息获取逻辑已移至ResponseMessage类
    // 请使用ResponseMessage::getDefaultMessage()获取默认消息
}
