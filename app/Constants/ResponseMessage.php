<?php

declare(strict_types=1);
/**
 * 响应消息常量类
 * 统一管理API返回的所有消息内容
 */
namespace App\Constants;

class ResponseMessage
{
    // 成功消息
    public const SUCCESS = '操作成功';
    public const CREATE_SUCCESS = '创建成功';
    public const UPDATE_SUCCESS = '更新成功';
    public const DELETE_SUCCESS = '删除成功';
    public const QUERY_SUCCESS = '查询成功';
    public const LOGIN_SUCCESS = '登录成功';
    public const LOGOUT_SUCCESS = '登出成功';
    public const UPLOAD_SUCCESS = '上传成功';
    
    // 评论相关消息
    public const COMMENT_LIST_SUCCESS = '获取评论列表成功';
    public const COMMENT_CREATE_SUCCESS = '评论创建成功，等待审核';
    public const COMMENT_SHOW_SUCCESS = '获取评论详情成功';
    public const COMMENT_UPDATE_SUCCESS = '评论更新成功';
    public const COMMENT_DELETE_SUCCESS = '评论删除成功';
    public const COMMENT_APPROVE_SUCCESS = '评论审核通过';
    public const COMMENT_REJECT_SUCCESS = '评论已拒绝';
    public const COMMENT_BATCH_REVIEW_SUCCESS = '批量审核完成';
    public const COMMENT_REPLIES_SUCCESS = '获取回复列表成功';
    public const COMMENT_REPLY_CREATE_SUCCESS = '回复创建成功，等待审核';
    public const PENDING_COMMENTS_SUCCESS = '获取待审核评论列表成功';
    
    // 参数错误消息
    public const PARAM_ERROR = '参数错误';
    public const PARAM_REQUIRED = '缺少必要参数';
    public const PARAM_FORMAT_ERROR = '参数格式错误';
    public const PARAM_TYPE_ERROR = '参数类型错误';
    public const PARAM_OUT_OF_RANGE = '参数超出范围';
    public const EMAIL_FORMAT_ERROR = '邮箱格式不正确';
    
    // 评论参数错误
    public const COMMENT_PARAM_REQUIRED = '请填写必要的评论信息';
    public const COMMENT_STATUS_INVALID = '审核状态只能是1（通过）或2（拒绝）';
    public const COMMENT_BATCH_PARAM_REQUIRED = '请提供正确的评论ID列表和审核状态';
    public const COMMENT_REPLY_CONTENT_REQUIRED = '回复内容不能为空';
    public const COMMENT_CONTENT_LENGTH_EXCEEDED = '评论内容不能超过1000字';
    
    // 认证授权错误
    public const UNAUTHORIZED = '未授权访问';
    public const TOKEN_EXPIRED = '令牌已过期';
    public const TOKEN_INVALID = '无效的令牌';
    public const NO_PERMISSION = '无权操作';
    public const USER_NOT_LOGIN = '用户未登录';
    public const LOGIN_REQUIRED = '请先登录';
    
    // 评论权限错误
    public const COMMENT_VIEW_FORBIDDEN = '无权查看该评论';
    public const COMMENT_UPDATE_FORBIDDEN = '无权更新该评论';
    public const COMMENT_DELETE_FORBIDDEN = '无权删除该评论';
    
    // 资源错误
    public const RESOURCE_NOT_FOUND = '资源不存在';
    public const RESOURCE_EXISTS = '资源已存在';
    public const RESOURCE_DELETED = '资源已被删除';
    public const RESOURCE_UPDATE_FAILED = '资源更新失败';
    public const RESOURCE_CREATE_FAILED = '资源创建失败';
    
    // 评论资源错误
    public const COMMENT_NOT_FOUND = '评论不存在';
    public const COMMENT_APPROVE_FAILED = '评论不存在或审核失败';
    public const COMMENT_REJECT_FAILED = '评论不存在或操作失败';
    
    // 服务器错误
    public const SERVER_ERROR = '服务器内部错误';
    public const DATABASE_ERROR = '数据库操作失败';
    public const SERVICE_UNAVAILABLE = '服务暂不可用';
    public const TIMEOUT = '请求超时';
    
    // 评论服务器错误
    public const COMMENT_LIST_FAILED = '获取评论列表失败';
    public const COMMENT_CREATE_FAILED = '创建评论失败';
    public const COMMENT_SHOW_FAILED = '获取评论详情失败';
    public const COMMENT_UPDATE_FAILED = '评论更新失败';
    public const COMMENT_DELETE_FAILED = '评论删除失败';
    public const COMMENT_APPROVE_ERROR = '审核评论失败';
    public const COMMENT_REJECT_ERROR = '操作失败';
    public const COMMENT_BATCH_REVIEW_FAILED = '批量审核失败';
    public const COMMENT_REPLIES_FAILED = '获取回复列表失败';
    public const COMMENT_REPLY_CREATE_FAILED = '创建回复失败';
    public const PENDING_COMMENTS_FAILED = '获取待审核评论列表失败';
    
    // 文件相关错误
    public const FILE_UPLOAD_FAILED = '文件上传失败';
    public const FILE_TYPE_INVALID = '无效的文件类型';
    public const FILE_SIZE_EXCEEDED = '文件大小超出限制';
    public const FILE_NOT_FOUND = '文件不存在';
    
    // 业务逻辑错误
    public const BUSINESS_ERROR = '业务处理失败';
    public const DATA_DUPLICATE = '数据重复';
    public const DATA_EXISTS = '数据已存在';
    public const OPERATION_FAILED = '操作失败';
    
    /**
     * 根据状态码获取默认消息
     * @param int $code 状态码
     * @return string 默认消息
     */
    public static function getDefaultMessage(int $code): string
    {
        $messageMap = [
            // 成功状态码
            StatusCode::SUCCESS => self::SUCCESS,
            StatusCode::CREATED => self::CREATE_SUCCESS,
            StatusCode::NO_CONTENT => self::SUCCESS,
            
            // 客户端错误
            StatusCode::BAD_REQUEST => self::PARAM_ERROR,
            StatusCode::UNAUTHORIZED => self::UNAUTHORIZED,
            StatusCode::FORBIDDEN => self::NO_PERMISSION,
            StatusCode::NOT_FOUND => self::RESOURCE_NOT_FOUND,
            StatusCode::METHOD_NOT_ALLOWED => self::OPERATION_FAILED,
            StatusCode::UNPROCESSABLE_ENTITY => self::PARAM_ERROR,
            
            // 服务器错误
            StatusCode::INTERNAL_SERVER_ERROR => self::SERVER_ERROR,
            StatusCode::BAD_GATEWAY => self::SERVER_ERROR,
            StatusCode::SERVICE_UNAVAILABLE => self::SERVICE_UNAVAILABLE,
            StatusCode::GATEWAY_TIMEOUT => self::TIMEOUT,
            
            // 业务错误
            StatusCode::BUSINESS_ERROR => self::BUSINESS_ERROR,
            StatusCode::DATA_DUPLICATE => self::DATA_DUPLICATE,
            StatusCode::DATA_EXISTS => self::DATA_EXISTS,
        ];
        
        return $messageMap[$code] ?? self::SERVER_ERROR;
    }
}