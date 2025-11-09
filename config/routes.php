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
use Hyperf\HttpServer\Router\Router;

// 健康检查路由
Router::addRoute(['GET', 'HEAD', 'POST'], '/', function() {
    return ['status' => 'ok', 'message' => 'Service is running'];
});

Router::get('/favicon.ico', function () {
    return '';
});

// 认证相关路由将在控制器中重新配置，移除版本号v1

// OAuth相关路由已移除 - OAuthController不存在

// 评论管理相关路由
Router::addGroup('/api/comments', function () {
    // 获取评论列表（公开访问）
    Router::get('', 'App\Controller\Api\CommentController@index');

    // 创建评论
    Router::post('', 'App\Controller\Api\CommentController@store');

    // 获取评论详情
    Router::get('/{id}', 'App\Controller\Api\CommentController@show');

    // 更新评论
    Router::put('/{id}', 'App\Controller\Api\CommentController@update');

    // 删除评论
    Router::delete('/{id}', 'App\Controller\Api\CommentController@destroy');

    // 获取待审核评论列表
    Router::get('/pending/list', 'App\Controller\Api\CommentController@getPendingComments');

    // 审核通过评论
    Router::put('/{id}/approve', 'App\Controller\Api\CommentController@approveComment');

    // 拒绝评论
    Router::put('/{id}/reject', 'App\Controller\Api\CommentController@rejectComment');

    // 批量审核评论
    Router::post('/batch-review', 'App\Controller\Api\CommentController@batchReviewComments');

    // 获取评论的回复
    Router::get('/{id}/replies', 'App\Controller\Api\CommentController@getReplies');

    // 回复评论
    Router::post('/{id}/reply', 'App\Controller\Api\CommentController@replyComment');
});

// API 路由组 - 移除版本号v1
Router::addGroup('/api', function () {
    // 邮箱相关路由
    Router::post('/email/send-verify-code', 'App\Controller\Api\EmailController@sendVerifyCode');
    Router::post('/email/send', 'App\Controller\Api\EmailController@sendEmail');

    // 订阅相关路由
    Router::post('/subscribe/blog', 'App\Controller\Api\SubscribeController@subscribeBlog');
    Router::get('/subscribe/confirm', 'App\Controller\Api\SubscribeController@confirmSubscribe');

    // 联系表单相关路由
    Router::post('/contact/submit', 'App\Controller\Api\ContactController@submitContact');

    // 社交媒体分享路由
    Router::get('/social/share/config', 'App\Controller\Api\SocialShareController@getShareConfig');

    // 脑图相关路由
    Router::get('/mind-map/root-nodes', 'App\Controller\Api\MindMapController@getRootNodes');
    Router::get('/mind-map/{id}', 'App\Controller\Api\MindMapController@getMindMapData'); // 修正方法名为getMindMapData

    // 系统相关路由
    Router::get('/system/statistics', 'App\Controller\Api\SystemController@getStatistics');

    // 配置相关路由
    Router::get('/config/get', 'App\Controller\Api\ConfigController@getConfig');
    // 注意：ConfigController中只实现了getConfig方法，updateConfig和batchUpdateConfig需要在控制器中实现后再启用

    // 权限管理相关路由
    // 获取角色列表 - 仅管理员可访问
    Router::get('/permission/roles', 'App\Controller\Api\PermissionController@getRoles', ['middleware' => ['admin_permission']]);
    // 获取用户角色信息 - 需要认证
    Router::get('/permission/user-role', 'App\Controller\Api\PermissionController@getUserRole', ['middleware' => ['permission']]);
    // 更新用户角色 - 仅管理员可访问
    Router::post('/permission/update-role', 'App\Controller\Api\PermissionController@updateRole', ['middleware' => ['admin_permission']]);
    // 权限列表 - 仅管理员可访问
    Router::get('/permission/list', 'App\Controller\Api\PermissionController@getPermissions', ['middleware' => ['admin_permission']]);
    // 分配权限 - 仅管理员可访问
    Router::post('/permission/assign', 'App\Controller\Api\PermissionController@assignPermission', ['middleware' => ['admin_permission']]);
    // 检查用户权限 - 需要认证
    Router::post('/permission/check', 'App\Controller\Api\PermissionController@checkPermission', ['middleware' => ['permission']]);
});

// 认证相关路由 - 显式配置以确保API正常工作
Router::post('/api/auth/login', 'App\Controller\Api\AuthController@login');
Router::post('/api/auth/register', 'App\Controller\Api\AuthController@register');
Router::delete('/api/auth/logout', 'App\Controller\Api\AuthController@logout');
Router::post('/api/auth/change-password', 'App\Controller\Api\AuthController@changePassword');
Router::get('/api/auth/me', 'App\Controller\Api\AuthController@me');
// 前端已修改为使用/api/auth/me，不再需要兼容路由
Router::post('/api/auth/refresh', 'App\Controller\Api\AuthController@refresh');
Router::put('/api/auth/profile', 'App\Controller\Api\AuthController@updateProfile');
