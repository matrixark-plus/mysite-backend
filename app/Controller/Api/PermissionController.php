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

use App\Constants\StatusCode;
use App\Controller\AbstractController;
use App\Controller\Api\Validator\PermissionValidator;
use App\Service\PermissionService;
use App\Traits\LogTrait;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Validation\ValidationException;

/**
 * @Controller(prefix="/api/permission")
 */
class PermissionController extends AbstractController
{
    use LogTrait;

    /**
     * @var PermissionService
     * @Inject
     */
    protected $permissionService;

    /**
     * @var PermissionValidator
     * @Inject
     */
    protected $validator;

    /**
     * 获取角色列表
     * 仅管理员可访问.
     *
     * @RequestMapping(path="/roles", methods={"GET"})
     */
    public function getRoles()
    {
        try {
            // 验证请求参数
            $params = $this->request->all();
            $validatedData = $this->validator->validateGetRoles($params);

            $roles = $this->permissionService->getAllRoles();
            return $this->success($roles);
        } catch (ValidationException $e) {
            return $this->validationError($e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('获取角色列表异常', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ], $e, 'permission');
            return $this->error('获取角色列表失败');
        }
    }

    /**
     * 获取用户角色信息
     * 需要认证
     *
     * @RequestMapping(path="/user-role", methods={"GET"})
     */
    public function getUserRole()
    {
        try {
            // 使用验证器验证参数
            $data = $this->validator->validateGetUserRole($this->request->all());
            $userId = $data['user_id'] ?? null;

            // 如果没有指定用户ID，则获取当前用户的角色
            $currentUser = $this->request->getAttribute('user');
            if (! $userId) {
                $userId = $currentUser->id ?? null;
            }

            // 检查权限：只能查看自己的角色或管理员可以查看所有用户的角色
            $currentUserId = $currentUser->id ?? null;
            $currentUserRole = $currentUser->role ?? '';
            if ($currentUserRole !== 'admin' && $currentUserId != $userId) {
                return $this->forbidden('无权查看其他用户的角色信息');
            }

            $userRoleInfo = $this->permissionService->getUserRoleInfo($userId);
            return $this->success($userRoleInfo);
        } catch (ValidationException $e) {
            // 参数验证失败
            return $this->validationError($e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('获取用户角色异常', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ], $e, 'permission');
            return $this->error('获取用户角色失败');
        }
    }

    /**
     * 更新用户角色
     * 仅管理员可访问.
     *
     * @RequestMapping(path="/update-role", methods={"POST"})
     */
    public function updateRole()
    {
        try {
            // 使用验证器验证参数
            $data = $this->validator->validateUpdateRole($this->request->all());

            $result = $this->permissionService->updateUserRole($data['user_id'], $data['role']);

            if ($result) {
                return $this->success(['updated' => true], '用户角色更新成功');
            }
            return $this->error('用户角色更新失败');
        } catch (ValidationException $e) {
            // 参数验证失败
            return $this->validationError($e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('更新用户角色异常', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'user_id' => $this->request->input('user_id'),
                'role' => $this->request->input('role'),
            ], $e, 'permission');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, $e->getMessage());
        }
    }

    /**
     * 获取权限列表
     * 仅管理员可访问.
     *
     * @RequestMapping(path="/list", methods={"GET"})
     */
    public function getPermissions()
    {
        try {
            // 获取所有角色列表（系统使用角色而非权限）
            $roles = $this->permissionService->getAllRoles();
            return $this->success($roles);
        } catch (Exception $e) {
            $this->logError('获取角色列表异常', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ], $e, 'permission');
            return $this->error('获取角色列表失败');
        }
    }

    /**
     * 分配权限
     * 仅管理员可访问.
     *
     * @RequestMapping(path="/assign", methods={"POST"})
     */
    public function assignPermission()
    {
        try {
            // 使用验证器验证参数
            $data = $this->validator->validateAssignPermission($this->request->all());

            // 系统使用角色而非权限，所以更新用户角色
            // 这里简化处理，假设权限数组中的第一个元素是角色
            $role = $data['permissions'][0] ?? 'user';
            $result = $this->permissionService->updateUserRole(
                $data['user_id'],
                $role
            );

            if ($result) {
                return $this->success(['assigned' => true], '权限分配成功');
            }
            return $this->error('权限分配失败');
        } catch (ValidationException $e) {
            // 参数验证失败
            return $this->validationError($e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('分配权限异常', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'user_id' => $this->request->input('user_id'),
            ], $e, 'permission');
            return $this->error($e->getMessage());
        }
    }

    /**
     * 检查用户权限
     * 需要认证
     *
     * @RequestMapping(path="/check", methods={"POST"})
     */
    public function checkPermission()
    {
        try {
            // 使用验证器验证参数
            $permissionData = ['permission' => $this->request->input('permission')];
            $data = $this->validator->validateCheckPermission($permissionData);
            $requiredPermission = $data['permission'];

            // 获取当前用户信息
            $currentUser = $this->request->getAttribute('user');

            // 管理员拥有所有权限
            $currentUserRole = $currentUser->role ?? '';
            if ($currentUserRole === 'admin') {
                return $this->success(['has_permission' => true]);
            }

            // 检查用户是否拥有管理员权限
            $hasPermission = $this->permissionService->isAdmin($currentUser);

            return $this->success(['has_permission' => $hasPermission]);
        } catch (ValidationException $e) {
            // 参数验证失败
            return $this->validationError($e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('检查权限异常', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ], $e, 'permission');
            return $this->error('检查权限失败');
        }
    }
}
