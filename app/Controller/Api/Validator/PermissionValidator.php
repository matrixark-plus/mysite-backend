<?php

declare(strict_types=1);
/**
 * 权限管理相关的参数验证器.
 */

namespace App\Controller\Api\Validator;

use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\ValidationException;
use Hyperf\Di\Annotation\Inject;

/**
 * 权限管理相关的参数验证器.
 */
class PermissionValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validatorFactory;


    /**
     * 验证获取用户角色信息的请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateGetUserRole(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'user_id' => 'nullable|integer|min:1',
        ], [
            'user_id.integer' => '用户ID必须是整数',
            'user_id.min' => '用户ID必须大于0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证更新用户角色的请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateUpdateRole(array $data): array
    {
        // 有效的角色列表
        $validRoles = ['admin', 'editor', 'user'];
        $validRolesStr = implode(', ', $validRoles);

        $validator = $this->validatorFactory->make($data, [
            'user_id' => 'required|integer|min:1',
            'role' => "required|string|in:{$validRolesStr}",
        ], [
            'user_id.required' => '缺少必要参数：user_id',
            'user_id.integer' => '用户ID必须是整数',
            'user_id.min' => '用户ID必须大于0',
            'role.required' => '缺少必要参数：role',
            'role.string' => '角色必须是字符串',
            'role.in' => "无效的角色类型，有效值为：{$validRolesStr}",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证获取角色列表参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateGetRoles(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'status' => 'nullable|integer|in:0,1',
            'with_permissions' => 'nullable|boolean',
        ], [
            'status.in' => '状态值无效，只能是0或1',
            'with_permissions.boolean' => 'with_permissions必须是布尔值',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证分配权限的请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateAssignPermission(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'user_id' => 'required|integer|min:1',
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ], [
            'user_id.required' => '缺少必要参数：user_id',
            'user_id.integer' => '用户ID必须是整数',
            'user_id.min' => '用户ID必须大于0',
            'permissions.required' => '缺少必要参数：permissions',
            'permissions.array' => '权限列表必须是数组',
            'permissions.*.string' => '权限项必须是字符串',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证检查权限的请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateCheckPermission(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'permission' => 'required|string',
        ], [
            'permission.required' => '缺少必要参数：permission',
            'permission.string' => '权限必须是字符串',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
