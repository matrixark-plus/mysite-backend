<?php

declare(strict_types=1);
/**
 * 联系控制器
 * 处理联系表单相关的管理操作
 */

namespace App\Controller\Api;

use App\Constants\StatusCode;
use App\Controller\AbstractController;
use App\Controller\Api\Validator\ContactValidator;
use App\Middleware\JwtAuthMiddleware;
use App\Service\ContactService;
use App\Traits\LogTrait;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\ValidationException;
use Qbhy\HyperfAuth\AuthManager;

/**
 * 联系控制器（管理员功能）
 * @Controller(prefix="/api/admin/contact-forms")
 */
class ContactController extends AbstractController
{
    use LogTrait;

    /**
     * @Inject
     * @var ContactService
     */
    protected $contactService;

    /**
     * @Inject
     * @var AuthManager
     */
    protected $auth;

    /**
     * @Inject
     * @var ContactValidator
     */
    protected $validator;

    /**
     * 获取联系表单列表
     * @RequestMapping(path="", methods={"GET"})
     * @Middleware(middleware=JwtAuthMiddleware::class)
     */
    public function getContactForms(RequestInterface $request)
    {
        try {
            // 权限验证：仅管理员可访问
            $currentUser = $this->auth->user();
            if (! $currentUser || ! $currentUser->is_admin) {
                return $this->unauthorized('无权访问');
            }

            // 参数验证
            $validatedData = $this->validator->validateContactList($request->all());
            
            // 获取分页参数
            $page = (int) ($validatedData['page'] ?? 1);
            $limit = (int) ($validatedData['limit'] ?? 10);
            
            // 构建查询条件
            $conditions = [];
            if (isset($validatedData['status'])) {
                $conditions['status'] = $validatedData['status'];
            }
            if (isset($validatedData['search'])) {
                $conditions['search'] = $validatedData['search'];
            }
            if (isset($validatedData['start_date'])) {
                $conditions['start_date'] = $validatedData['start_date'];
            }
            if (isset($validatedData['end_date'])) {
                $conditions['end_date'] = $validatedData['end_date'];
            }

            // 获取列表数据
            $result = $this->contactService->getContactList($page, $limit, $conditions);

            return $this->success($result, '获取成功');
        } catch (ValidationException $e) {
            return $this->fail(StatusCode::VALIDATION_ERROR, $e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('获取联系表单列表异常', ['message' => $e->getMessage()], $e, 'contact');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '服务器内部错误');
        }
    }

    /**
     * 获取单个联系表单详情
     * @RequestMapping(path="/{id}", methods={"GET"})
     * @Middleware(middleware=JwtAuthMiddleware::class)
     */
    public function getContactForm(int $id)
    {
        try {
            // 权限验证：仅管理员可访问
            $currentUser = $this->auth->user();
            if (! $currentUser || ! $currentUser->is_admin) {
                return $this->unauthorized('无权访问');
            }

            // 参数验证
            $validatedData = $this->validator->validateContactList(['id' => $id]);
            
            // 获取联系表单详情
            $contactForm = $this->contactService->getContactFormDetail($validatedData['id']);
            
            if (! $contactForm['success']) {
                return $this->fail(StatusCode::NOT_FOUND, $contactForm['message'] ?? '联系表单不存在');
            }

            return $this->success($contactForm['data'], '获取成功');
        } catch (ValidationException $e) {
            return $this->fail(StatusCode::VALIDATION_ERROR, $e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('获取联系表单详情异常', ['message' => $e->getMessage()], $e, 'contact');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '服务器内部错误');
        }
    }

    /**
     * 更新联系表单状态
     * @RequestMapping(path="/{id}/status", methods={"PUT"})
     * @Middleware(middleware=JwtAuthMiddleware::class)
     */
    public function updateContactStatus(int $id, RequestInterface $request)
    {
        try {
            // 权限验证：仅管理员可访问
            $currentUser = $this->auth->user();
            if (! $currentUser || ! $currentUser->is_admin) {
                return $this->unauthorized('无权访问');
            }

            // 参数验证
            $validatedData = $this->validator->validateUpdateStatus($request->all());
            
            // 更新状态 - 使用现有的markAsProcessed方法
            // 如果状态为1（已处理），则标记为已处理
            if ($validatedData['status'] == 1) {
                $result = $this->contactService->markAsProcessed($id);
                
                if (! $result) {
                    return $this->fail(StatusCode::NOT_FOUND, '联系表单不存在或更新失败');
                }
            } else {
                // 如果需要其他状态更新，可以扩展ContactService
                return $this->fail(StatusCode::VALIDATION_ERROR, '暂不支持的状态更新');
            }

            return $this->success([], '状态更新成功');
        } catch (ValidationException $e) {
            return $this->fail(StatusCode::VALIDATION_ERROR, $e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('更新联系表单状态异常', ['message' => $e->getMessage()], $e, 'contact');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '服务器内部错误');
        }
    }

    /**
     * 删除联系表单
     * @RequestMapping(path="/{id}", methods={"DELETE"})
     * @Middleware(middleware=JwtAuthMiddleware::class)
     */
    public function deleteContactForm(int $id)
    {
        try {
            // 权限验证：仅管理员可访问
            $currentUser = $this->auth->user();
            if (! $currentUser || ! $currentUser->is_admin) {
                return $this->unauthorized('无权访问');
            }

            // 参数验证
            $validatedData = $this->validator->validateContactList(['id' => $id]);
            
            // 删除联系表单
            $result = $this->contactService->deleteContactForm($validatedData['id']);
            
            if (! $result['success']) {
                return $this->fail(StatusCode::NOT_FOUND, $result['message'] ?? '联系表单不存在或删除失败');
            }

            return $this->success([], '删除成功');
        } catch (ValidationException $e) {
            return $this->fail(StatusCode::VALIDATION_ERROR, $e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('删除联系表单异常', ['message' => $e->getMessage()], $e, 'contact');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '服务器内部错误');
        }
    }

    /**
     * 批量删除联系表单
     * @RequestMapping(path="/batch-delete", methods={"DELETE"})
     * @Middleware(middleware=JwtAuthMiddleware::class)
     */
    public function batchDeleteContactForms(RequestInterface $request)
    {
        try {
            // 权限验证：仅管理员可访问
            $currentUser = $this->auth->user();
            if (! $currentUser || ! $currentUser->is_admin) {
                return $this->unauthorized('无权访问');
            }

            // 获取并验证ID列表
            $ids = $request->input('ids', []);
            if (! is_array($ids) || empty($ids)) {
                return $this->fail(StatusCode::VALIDATION_ERROR, '请选择要删除的联系表单');
            }
            
            // 调用服务层批量删除方法
            $result = $this->contactService->batchDeleteContactForms($ids);
            
            if (! $result['success']) {
                return $this->fail(StatusCode::VALIDATION_ERROR, $result['message']);
            }

            return $this->success(['deleted_count' => $result['deleted_count']], $result['message']);
        } catch (Exception $e) {
            $this->logError('批量删除联系表单异常', ['message' => $e->getMessage()], $e, 'contact');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '服务器内部错误');
        }
    }
    
    /**
     * 提交联系表单（公开接口，无需登录）
     * @RequestMapping(path="/submit", methods={"POST"})
     */
    public function submitContactForm(RequestInterface $request)
    {
        try {
            // 验证提交数据
            $validatedData = $this->validator->validateContactForm($request->all());
            
            // 添加IP地址信息
            $validatedData['ip'] = $request->getServerParams()['remote_addr'] ?? '';
            
            // 调用服务层提交表单
            $result = $this->contactService->submitContactForm($validatedData);
            
            if (! $result['success']) {
                return $this->fail(StatusCode::VALIDATION_ERROR, $result['message']);
            }
            
            return $this->success([], $result['message']);
        } catch (ValidationException $e) {
            return $this->fail(StatusCode::VALIDATION_ERROR, $e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('提交联系表单异常', ['message' => $e->getMessage()], $e, 'contact');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '服务器内部错误');
        }
    }
}
