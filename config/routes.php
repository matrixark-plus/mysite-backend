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
Router::addRoute(['GET', 'HEAD'], '/', function () {
    return ['status' => 'ok', 'message' => 'Service is running'];
});

// Favicon路由
Router::get('/favicon.ico', function () {
    return '';
});

// API路由主组
Router::addGroup('/api', function () {
    // ======================================
    // 认证相关路由
    // ======================================
    Router::addGroup('/auth', function () {
        Router::post('/login', 'App\Controller\Api\AuthController@login');
        Router::post('/register', 'App\Controller\Api\AuthController@register');
        Router::delete('/logout', 'App\Controller\Api\AuthController@logout');
        Router::post('/change-password', 'App\Controller\Api\AuthController@changePassword');
        Router::get('/me', 'App\Controller\Api\AuthController@me');
        Router::post('/refresh', 'App\Controller\Api\AuthController@refresh');
        Router::put('/profile', 'App\Controller\Api\AuthController@updateProfile');
    });

    // ======================================
    // 评论管理相关路由
    // ======================================
    Router::addGroup('/comments', function () {
        // 公开访问的评论接口
        Router::get('', 'App\Controller\Api\CommentController@index');
        Router::post('', 'App\Controller\Api\CommentController@store');
        Router::get('/{id}', 'App\Controller\Api\CommentController@show');
        Router::get('/{id}/replies', 'App\Controller\Api\CommentController@getReplies');
        Router::post('/{id}/reply', 'App\Controller\Api\CommentController@replyComment');

        // 需要认证的评论接口
        Router::put('/{id}', 'App\Controller\Api\CommentController@update');
        Router::delete('/{id}', 'App\Controller\Api\CommentController@destroy');

        // 管理员审核接口
        Router::get('/pending/list', 'App\Controller\Api\CommentController@getPendingComments');
        Router::put('/{id}/approve', 'App\Controller\Api\CommentController@approveComment');
        Router::put('/{id}/reject', 'App\Controller\Api\CommentController@rejectComment');
        Router::post('/batch-review', 'App\Controller\Api\CommentController@batchReviewComments');
    });

    // ======================================
    // 邮箱相关路由
    // ======================================
    Router::addGroup('/email', function () {
        Router::post('/send-verify-code', 'App\Controller\Api\EmailController@sendVerifyCode');
        Router::post('/send', 'App\Controller\Api\EmailController@sendEmail');
    });

    // ======================================
    // 订阅相关路由
    // ======================================
    Router::addGroup('/subscribe', function () {
        Router::post('/blog', 'App\Controller\Api\SubscribeController@subscribeBlog');
        Router::get('/confirm', 'App\Controller\Api\SubscribeController@confirmSubscribe');
    });

    // ======================================
    // 联系表单相关路由
    // ======================================
    Router::post('/contact/submit', 'App\Controller\Api\ContactController@submitContact');

    // ======================================
    // 社交媒体分享路由
    // ======================================
    Router::get('/social/share/config', 'App\Controller\Api\SocialShareController@getShareConfig');

    // ======================================
    // 脑图相关路由
    // ======================================
    Router::addGroup('/mind-map', function () {
        Router::get('/root-nodes', 'App\Controller\Api\MindMapController@getRootNodes');
        Router::get('/{id}', 'App\Controller\Api\MindMapController@getMindMapData');
    });

    // ======================================
    // 系统相关路由
    // ======================================
    Router::get('/system/statistics', 'App\Controller\Api\SystemController@getStatistics');

    // ======================================
    // 配置相关路由
    // ======================================
    Router::get('/config/get', 'App\Controller\Api\ConfigController@getConfig');

    // ======================================
    // 权限管理相关路由
    // ======================================
    Router::addGroup('/permission', function () {
        // 管理员权限路由
        Router::get('/roles', 'App\Controller\Api\PermissionController@getRoles', ['middleware' => ['admin_permission']]);
        Router::post('/update-role', 'App\Controller\Api\PermissionController@updateRole', ['middleware' => ['admin_permission']]);
        Router::get('/list', 'App\Controller\Api\PermissionController@getPermissions', ['middleware' => ['admin_permission']]);
        Router::post('/assign', 'App\Controller\Api\PermissionController@assignPermission', ['middleware' => ['admin_permission']]);

        // 需要认证的路由
        Router::get('/user-role', 'App\Controller\Api\PermissionController@getUserRole', ['middleware' => ['permission']]);
        Router::post('/check', 'App\Controller\Api\PermissionController@checkPermission', ['middleware' => ['permission']]);
    });
});
