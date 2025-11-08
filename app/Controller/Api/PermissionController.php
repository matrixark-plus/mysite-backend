<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Service\PermissionService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use App\Constants\StatusCode;
use App\Controller\Api\Validator\PermissionValidator;
use Hyperf\Validation\ValidationException;

/**
 * @Controller(prefix="/api/permission")
 */
class PermissionController extends AbstractController
{
    use \App\Traits\LogTrait;
    
    /**
     * @Inject
     * @var PermissionService
     */
    protected $permissionService;
    
    /**
     * @Inject
     * @var PermissionValidator
     */
    protected $validator;
    
    /**
     * 获取角色列表
     * 仅管理员可访问
     * 
     * @RequestMapping(path="/roles", methods={"GET"})
     */
    public function getRoles(RequestInterface $request): ResponseInterface
    {
        try {
            $roles = $this->permissionService->getAllRoles();
            return $this->success($roles);
        } catch (\Exception $e) {
            $this->logError('获取角色列表异常', [
                'message' => $e->getMessage(),
                'exception' => get_class($e)
            ], $e, 'permission');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取角色列表失败');
        }
    }
    
    /**
     * 获取用户角色信息
     * 需要认证
     * 
     * @RequestMapping(path="/user-role", methods={"GET"})
     */
    public function getUserRole(RequestInterface $request): ResponseInterface
    {
        try {
            // 使用验证器验证参数
            $data = $this->validator->validateGetUserRole($request->all());
            $userId = $data['user_id'] ?? null;
            
            // 如果没有指定用户ID，则获取当前用户的角色
            if (!$userId) {
                $user = $this->request->getAttribute('user');
                if (!$user) {
                    return $this->fail(StatusCode::UNAUTHORIZED, '请先登录');
                }
                $userId = $user->id;
            }
            
            // 检查权限：只能查看自己的角色或管理员可以查看所有用户的角色
            $currentUser = $this->request->getAttribute('user');
            if ($currentUser->role !== 'admin' && $currentUser->id != $userId) {
                return $this->fail(StatusCode::FORBIDDEN, '无权查看其他用户的角色信息');
            }
            
            $userRoleInfo = $this->permissionService->getUserRoleInfo($userId);
            return $this->success($userRoleInfo);
        } catch (ValidationException $e) {
            // 参数验证失败
            return $this->fail(StatusCode::VALIDATION_ERROR, $e->validator->errors()-u003efirst());
        } catch (\Exception $e) {
            $this->logError('获取用户角色异常', [
                'message' => $e->getMessage(),
                'exception' => get_class($e)
            ], $e, 'permission');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取用户角色失败');
        }
    }
    
    /**
     * 更新用户角色
     * 仅管理员可访问
     * 
     * @RequestMapping(path="/update-role", methods={"POST"})
     */
    public function updateRole(RequestInterface $request): ResponseInterface
    {
        try {
            // 使用验证器验证参数
            $data = $this->validator->validateUpdateRole($request->all());
            
            $result = $this->permissionService->updateUserRole($data['user_id'], $data['role']);
            
            if ($result) {
                return $this->success(['updated' => true], '用户角色更新成功');
            } else {
                return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '用户角色更新失败');
            }
        } catch (ValidationException $e) {
            // 参数验证失败
            return $this->fail(StatusCode::VALIDATION_ERROR, $e->validator->errors()-u003efirst());
        } catch (\Exception $e) {
            $this->logError('更新用户角色异常', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'user_id' => $request->input('user_id'),
                'role' => $request->input('role')
            ], $e, 'permission');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, $e->getMessage());
        }
    }
}