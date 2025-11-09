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

namespace App\Controller\Api;

use App\Service\SubscriptionService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

/**
 * 订阅控制器
 * 处理订阅相关的HTTP请求.
 */
#[Controller(prefix: '/api/subscriptions')]
class SubscriptionController extends AbstractController
{
    /**
     * @Inject
     * @var SubscriptionService
     */
    protected $subscriptionService;

    /**
     * 创建订阅
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/', methods: ['POST'])]
    public function create(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $data = $request->all();
            $result = $this->subscriptionService->createSubscription($data);
            
            if ($result['success']) {
                return $this->success($result['message'], $result['data'] ?? []);
            } else {
                return $this->error($result['message'], [], 400);
            }
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * 确认订阅
     *
     * @param string $token 确认token
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/confirm/{token}', methods: ['GET'])]
    public function confirm(string $token, ResponseInterface $response)
    {
        try {
            $result = $this->subscriptionService->confirmSubscription($token);
            
            if ($result['success']) {
                // 返回成功页面或重定向
                return $response->write(
                    "<html><head><title>订阅确认成功</title></head><body><h1>{$result['message']}</h1></body></html>"
                );
            } else {
                // 返回错误页面
                return $response->write(
                    "<html><head><title>订阅确认失败</title></head><body><h1>{$result['message']}</h1></body></html>"
                );
            }
        } catch (\Exception $e) {
            return $response->write(
                "<html><head><title>系统错误</title></head><body><h1>系统异常，请稍后重试</h1></body></html>"
            );
        }
    }

    /**
     * 取消订阅
     *
     * @param string $token 取消订阅token
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/unsubscribe/{token}', methods: ['GET'])]
    public function unsubscribe(string $token, ResponseInterface $response)
    {
        try {
            $result = $this->subscriptionService->unsubscribe($token);
            
            // 返回页面
            return $response->write(
                "<html><head><title>取消订阅</title></head><body><h1>{$result['message']}</h1></body></html>"
            );
        } catch (\Exception $e) {
            return $response->write(
                "<html><head><title>系统错误</title></head><body><h1>系统异常，请稍后重试</h1></body></html>"
            );
        }
    }

    /**
     * 获取订阅列表（管理员功能）
     * 需要管理员权限验证
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/', methods: ['GET'])]
    public function list(RequestInterface $request)
    {
        try {
            // 这里应该添加管理员权限验证
            // $this->adminAuth();
            
            $conditions = [];
            
            // 获取查询参数
            if ($status = $request->input('status')) {
                $conditions['status'] = $status;
            }
            
            if ($email = $request->input('email')) {
                $conditions['email'] = ['like', "%{$email}%"];
            }
            
            $order = $request->input('order', 'created_at') ?: 'created_at';
            $sort = $request->input('sort', 'desc') ?: 'desc';
            $page = (int)($request->input('page', 1) ?: 1);
            $limit = (int)($request->input('limit', 20) ?: 20);
            
            $result = $this->subscriptionService->getSubscriptions($conditions, [$order => $sort], $page, $limit);
            
            if ($result['success']) {
                return $this->success('获取成功', $result['data']);
            } else {
                return $this->error($result['message'], [], 400);
            }
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * 重新发送确认邮件
     *
     * @param int $id 订阅ID
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/{id}/resend', methods: ['POST'])]
    public function resend(int $id)
    {
        try {
            // 这里应该添加管理员权限验证
            // $this->adminAuth();
            
            $result = $this->subscriptionService->resendConfirmation($id);
            
            if ($result['success']) {
                return $this->success($result['message']);
            } else {
                return $this->error($result['message'], [], 400);
            }
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }
}