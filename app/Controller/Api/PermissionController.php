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
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Validation\ValidationException;

/**
 * @Controller(prefix="/api/permission")
 */
class PermissionController extends AbstractController
{
    use LogTrait;

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
     * 仅管理员可访问.
     *
     * @RequestMapping(path="/roles", methods={"GET"})
     */
    public function getRoles(RequestInterface $request): ResponseInterface
    {
        try {
            $roles = $this->permissionService->getAllRoles();
            return $this->success($roles);
        } catch (Exception $e) {
            $this->logError('获取角色列表异常', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
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
            $currentUser = $this->request->getAttribute('user');
            if (! $userId) {
                $userId = $currentUser->id ?? null;
            }

            // 检查权限：只能查看自己的角色或管理员可以查看所有用户的角色
            $currentUserId = $currentUser->id ?? null;
            $currentUserRole = $currentUser->role ?? '';
            if ($currentUserRole !== 'admin' && $currentUserId != $userId) {
                return $this->fail(StatusCode::FORBIDDEN, '无权查看其他用户的角色信息');
            }

            $userRoleInfo = $this->permissionService->getUserRoleInfo($userId);
            return $this->success($userRoleInfo);
        } catch (ValidationException $e) {
            // 参数验证失败
            return $this->fail(StatusCode::VALIDATION_ERROR, $e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('获取用户角色异常', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ], $e, 'permission');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取用户角色失败');
        }
    }

    /**
     * 更新用户角色
     * 仅管理员可访问.
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
            }
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '用户角色更新失败');
        } catch (ValidationException $e) {
            // 参数验证失败
            return $this->fail(StatusCode::VALIDATION_ERROR, $e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('更新用户角色异常', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'user_id' => $request->input('user_id'),
                'role' => $request->input('role'),
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
    public function getPermissions(RequestInterface $request): ResponseInterface
    {
        try {
            // 从权限服务获取所有权限列表
            $permissions = $this->permissionService->getAllPermissions();
            return $this->success($permissions);
        } catch (Exception $e) {
            $this->logError('获取权限列表异常', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ], $e, 'permission');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取权限列表失败');
        }
    }

    /**
     * 分配权限
     * 仅管理员可访问.
     *
     * @RequestMapping(path="/assign", methods={"POST"})
     */
    public function assignPermission(RequestInterface $request): ResponseInterface
    {
        try {
            // 使用验证器验证参数
            $data = $this->validator->validateAssignPermission($request->all());

            // 分配权限
            $result = $this->permissionService->assignPermission(
                $data['user_id'],
                $data['permissions']
            );

            if ($result) {
                return $this->success(['assigned' => true], '权限分配成功');
            }
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '权限分配失败');
        } catch (ValidationException $e) {
            // 参数验证失败
            return $this->fail(StatusCode::VALIDATION_ERROR, $e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('分配权限异常', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'user_id' => $request->input('user_id'),
            ], $e, 'permission');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, $e->getMessage());
        }
    }

    /**
     * 检查用户权限
     * 需要认证
     *
     * @RequestMapping(path="/check", methods={"POST"})
     */
    public function checkPermission(RequestInterface $request): ResponseInterface
    {
        try {
            // 使用验证器验证参数
            $data = $this->validator->validateCheckPermission($request->all());
            $requiredPermission = $data['permission'];

            // 获取当前用户信息
            $currentUser = $this->request->getAttribute('user');

            // 管理员拥有所有权限
            $currentUserRole = $currentUser->role ?? '';
            if ($currentUserRole === 'admin') {
                return $this->success(['has_permission' => true]);
            }

            // 检查用户是否拥有指定权限
            $hasPermission = $this->permissionService->checkPermission($currentUser, $requiredPermission);

            return $this->success(['has_permission' => $hasPermission]);
        } catch (ValidationException $e) {
            // 参数验证失败
            return $this->fail(StatusCode::VALIDATION_ERROR, $e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('检查权限异常', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ], $e, 'permission');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '检查权限失败');
        }
    }
}
