<?php

declare(strict_types=1);

namespace App\Controller\Api\Validator;

use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\ValidationException;
use Psr\Container\ContainerInterface;

/**
 * 权限管理相关的参数验证器
 */
class PermissionValidator
{
    /**
     * @var ValidatorFactoryInterface
     */
    protected $validatorFactory;

    /**
     * 构造函数
     * 
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->validatorFactory = $container->get(ValidatorFactoryInterface::class);
    }

    /**
     * 验证获取用户角色信息的请求参数
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

        return $validator->valuated();
    }

    /**
     * 验证更新用户角色的请求参数
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

        return $validator->valuated();
    }
}