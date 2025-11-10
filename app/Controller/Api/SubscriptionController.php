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

use App\Controller\AbstractController;
use App\Controller\Api\Validator\SubscriptionValidator;
use App\Service\SubscriptionService;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Validation\ValidationException;

/**
 * 订阅控制器
 * 处理订阅相关的HTTP请求
 * @Controller(prefix="/api/subscriptions")
 */
class SubscriptionController extends AbstractController
{
    /**
     * @Inject
     * @var SubscriptionService
     */
    protected $subscriptionService;

    /**
     * @Inject
     * @var SubscriptionValidator
     */
    protected $validator;

    /**
     * 创建订阅.
     * @return ResponseInterface
     * @RequestMapping(path="/subscribe", methods={"POST"})
     */
    public function create(RequestInterface $request)
    {
        try {
            // 验证参数
            try {
                $data = $this->validator->validateCreateSubscription($request->all());
            } catch (ValidationException $e) {
                return $this->validationError($e->validator->errors()->first());
            }
            $result = $this->subscriptionService->createSubscription($data);

            if ($result['success']) {
                return $this->success($result['data'] ?? [], $result['message']);
            }
            return $this->validationError($result['message']);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * 确认订阅.
     * @param string $token 确认token
     * @return ResponseInterface
     * @RequestMapping(path="/confirm/{token}", methods={"GET"})
     */
    public function confirm(string $token)
    {
        try {
            $result = $this->subscriptionService->confirmSubscription($token);

            if ($result['success']) {
                // 返回成功页面或重定向
                return $this->response->write(
                    "<html><head><title>订阅确认成功</title></head><body><h1>{$result['message']}</h1></body></html>"
                );
            }
            // 返回错误页面
            return $this->response->write(
                "<html><head><title>订阅确认失败</title></head><body><h1>{$result['message']}</h1></body></html>"
            );
        } catch (Exception $e) {
            return $this->response->write(
                '<html><head><title>系统错误</title></head><body><h1>系统异常，请稍后重试</h1></body></html>'
            );
        }
    }

    /**
     * 取消订阅.
     *
     * @param string $token 取消订阅token
     * @return ResponseInterface
     */
    /** @RequestMapping(path="/unsubscribe/{token}", methods={"GET"}) */
    public function unsubscribe(string $token, ResponseInterface $response)
    {
        try {
            // 验证参数
            try {
                $this->validator->validateUnsubscribeToken(compact('token'));
            } catch (ValidationException $e) {
                return $response->write(
                    "<html><head><title>参数错误</title></head><body><h1>{$e->validator->errors()->first()}</h1></body></html>"
                );
            }
            $result = $this->subscriptionService->unsubscribe($token);

            // 返回页面
            return $response->write(
                "<html><head><title>取消订阅</title></head><body><h1>{$result['message']}</h1></body></html>"
            );
        } catch (Exception $e) {
            return $response->write(
                '<html><head><title>系统错误</title></head><body><h1>系统异常，请稍后重试</h1></body></html>'
            );
        }
    }

    /**
     * 获取订阅列表（管理员功能）
     * 需要管理员权限验证
     *
     * @return ResponseInterface
     */
    /** @RequestMapping(path="/", methods={"GET"}) */
    public function list(RequestInterface $request)
    {
        try {
            // 这里应该添加管理员权限验证
            // $this->adminAuth();

            // 验证参数
            try {
                $validatedData = $this->validator->validateSubscriptionList($request->all());
                $status = $validatedData['status'] ?? null;
                $email = $validatedData['email'] ?? null;
                $order = $validatedData['order'] ?? 'created_at';
                $sort = $validatedData['sort'] ?? 'desc';
                $page = $validatedData['page'] ?? 1;
                $limit = $validatedData['limit'] ?? 20;
            } catch (ValidationException $e) {
                return $this->validationError($e->validator->errors()->first());
            }

            $conditions = [];

            // 获取查询参数
            if ($status) {
                $conditions['status'] = $status;
            }

            if ($email) {
                $conditions['email'] = ['like', "%{$email}%"];
            }

            $result = $this->subscriptionService->getSubscriptions($conditions, [$order => $sort], $page, $limit);

            if ($result['success']) {
                return $this->success($result['data'], '获取成功');
            }
            return $this->validationError($result['message']);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * 重新发送确认邮件.
     *
     * @param int $id 订阅ID
     * @return ResponseInterface
     */
    /** @RequestMapping(path="/{id}/resend", methods={"POST"}) */
    public function resend(int $id)
    {
        try {
            // 这里应该添加管理员权限验证
            // $this->adminAuth();

            $result = $this->subscriptionService->resendConfirmation($id);

            if ($result['success']) {
                return $this->success(null, $result['message']);
            }
            return $this->validationError($result['message']);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }
}
