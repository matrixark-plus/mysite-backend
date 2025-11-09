<?php

declare(strict_types=1);

namespace App\Controller\Api\Validator;

use Hyperf\Validation\ValidationException;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;

/**
 * 思维导图根节点验证器
 * 处理思维导图根节点相关的请求参数验证
 */
class MindmapRootValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /**
     * 验证创建思维导图的参数
     *
     * @param array $data 请求数据
     * @throws ValidationException 验证失败时抛出异常
     */
    public function validateCreateMindmap(array $data): void
    {
        $validator = $this->validationFactory->make($data, [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'creator_id' => 'required|integer|gt:0',
            'is_public' => 'nullable|boolean',
        ], [
            'title.required' => '标题不能为空',
            'title.string' => '标题必须是字符串',
            'title.max' => '标题长度不能超过255个字符',
            'description.string' => '描述必须是字符串',
            'creator_id.required' => '创建者ID不能为空',
            'creator_id.integer' => '创建者ID必须是整数',
            'creator_id.gt' => '创建者ID必须大于0',
            'is_public.boolean' => '公开状态必须是布尔值',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * 验证分页参数
     *
     * @param array $params 请求参数
     * @return array 验证并标准化后的分页参数
     */
    public function validatePagination(array $params): array
    {
        $validator = $this->validationFactory->make($params, [
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ], [
            'page.integer' => '页码必须是整数',
            'page.min' => '页码必须大于等于1',
            'limit.integer' => '每页数量必须是整数',
            'limit.min' => '每页数量必须大于等于1',
            'limit.max' => '每页数量不能超过100',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // 标准化分页参数，提供默认值
        return [
            'page' => max(1, (int)($params['page'] ?? 1)),
            'limit' => max(1, min(100, (int)($params['limit'] ?? 20))),
        ];
    }

    /**
     * 验证思维导图ID
     *
     * @param int $id 思维导图ID
     * @throws ValidationException 验证失败时抛出异常
     */
    public function validateMindmapId(int $id): void
    {
        if ($id <= 0) {
            $validator = $this->validationFactory->make(['id' => $id], [
                'id' => 'required|integer|gt:0',
            ], [
                'id.gt' => '思维导图ID必须大于0',
            ]);
            throw new ValidationException($validator);
        }
    }

    /**
     * 验证更新思维导图的参数
     *
     * @param array $data 请求数据
     * @throws ValidationException 验证失败时抛出异常
     */
    public function validateUpdateMindmap(array $data): void
    {
        $validator = $this->validationFactory->make($data, [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'nullable|boolean',
        ], [
            'title.string' => '标题必须是字符串',
            'title.max' => '标题长度不能超过255个字符',
            'description.string' => '描述必须是字符串',
            'is_public.boolean' => '公开状态必须是布尔值',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * 验证用户ID
     *
     * @param int|null $userId 用户ID
     * @throws ValidationException 验证失败时抛出异常
     */
    public function validateUserId(?int $userId): void
    {
        if ($userId !== null && $userId <= 0) {
            $validator = $this->validationFactory->make(['user_id' => $userId], [
                'user_id' => 'required|integer|gt:0',
            ], [
                'user_id.gt' => '用户ID必须大于0',
            ]);
            throw new ValidationException($validator);
        }
    }
}