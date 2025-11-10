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

namespace App\Controller\Api\Validator;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Validation\ValidationException;

/**
 * 节点链接验证器
 * 处理NodeLinksController相关的请求参数验证
 */
class NodeLinksValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /**
     * 验证创建节点链接的参数.
     *
     * @param array $data 请求数据
     * @throws ValidationException 验证失败时抛出异常
     */
    public function validateCreateLink(array $data): void
    {
        $validator = $this->validationFactory->make($data, [
            'source_node_id' => 'required|integer|gt:0',
            'target_node_id' => 'required|integer|gt:0',
            'root_id' => 'required|integer|gt:0',
            'label' => 'nullable|string|max:100',
            'type' => 'nullable|string|max:50',
            'properties' => 'nullable|array',
        ], [
            'source_node_id.required' => '源节点ID不能为空',
            'source_node_id.integer' => '源节点ID必须是整数',
            'source_node_id.gt' => '源节点ID必须大于0',
            'target_node_id.required' => '目标节点ID不能为空',
            'target_node_id.integer' => '目标节点ID必须是整数',
            'target_node_id.gt' => '目标节点ID必须大于0',
            'root_id.required' => '脑图根节点ID不能为空',
            'root_id.integer' => '脑图根节点ID必须是整数',
            'root_id.gt' => '脑图根节点ID必须大于0',
            'label.string' => '链接标签必须是字符串',
            'label.max' => '链接标签长度不能超过100个字符',
            'type.string' => '链接类型必须是字符串',
            'type.max' => '链接类型长度不能超过50个字符',
            'properties.array' => '链接属性必须是数组格式',
        ]);

        // 检查源节点和目标节点不能相同
        if ($data['source_node_id'] ?? null && $data['target_node_id'] ?? null
            && $data['source_node_id'] === $data['target_node_id']) {
            $customValidator = $this->validationFactory->make($data, [
                'source_node_id' => 'required|different:target_node_id',
            ], [
                'source_node_id.different' => '源节点和目标节点不能相同',
            ]);
            throw new ValidationException($customValidator);
        }

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * 验证批量创建节点链接的数据.
     *
     * @param array $linksData 链接数据数组
     * @throws ValidationException 验证失败时抛出异常
     */
    public function validateBatchCreateLinks(array $linksData): void
    {
        if (empty($linksData)) {
            $validator = $this->validationFactory->make(['links' => $linksData], [
                'links' => 'required|array',
            ], [
                'links.required' => '请提供链接数据',
            ]);
            throw new ValidationException($validator);
        }

        if (count($linksData) > 100) {
            $validator = $this->validationFactory->make(['links' => $linksData], [
                'links' => 'max:100',
            ], [
                'links.max' => '批量创建的链接数量不能超过100条',
            ]);
            throw new ValidationException($validator);
        }

        // 验证每条链接数据
        foreach ($linksData as $index => $link) {
            if (! is_array($link)) {
                $validator = $this->validationFactory->make(['link' => $link], [
                    'link' => 'array',
                ], [
                    'link.array' => '第' . ($index + 1) . '条链接数据必须是数组格式',
                ]);
                throw new ValidationException($validator);
            }

            $validator = $this->validationFactory->make($link, [
                'source_node_id' => 'required|integer|gt:0|different:target_node_id',
                'target_node_id' => 'required|integer|gt:0',
                'root_id' => 'required|integer|gt:0',
                'label' => 'nullable|string|max:100',
                'type' => 'nullable|string|max:50',
                'properties' => 'nullable|array',
            ], [
                'source_node_id.different' => '第' . ($index + 1) . '条链接的源节点和目标节点不能相同',
            ]);

            if ($validator->fails()) {
                // 创建一个包含索引信息的错误消息
                $errorMessages = [];
                foreach ($validator->errors()->all() as $error) {
                    $errorMessages[] = '第' . ($index + 1) . '条链接数据无效: ' . $error;
                }

                // 创建一个新的验证器来包含自定义错误消息
                $errorValidator = $this->validationFactory->make([], []);
                foreach ($errorMessages as $error) {
                    $errorValidator->errors()->add('link', $error);
                }
                throw new ValidationException($errorValidator);
            }
        }
    }

    /**
     * 验证更新节点链接的参数.
     *
     * @param array $data 请求数据
     * @throws ValidationException 验证失败时抛出异常
     */
    public function validateUpdateLink(array $data): void
    {
        $validator = $this->validationFactory->make($data, [
            'label' => 'nullable|string|max:100',
            'type' => 'nullable|string|max:50',
            'properties' => 'nullable|array',
        ], [
            'label.string' => '链接标签必须是字符串',
            'label.max' => '链接标签长度不能超过100个字符',
            'type.string' => '链接类型必须是字符串',
            'type.max' => '链接类型长度不能超过50个字符',
            'properties.array' => '链接属性必须是数组格式',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * 验证链接ID.
     *
     * @param int $id 链接ID
     * @throws ValidationException 验证失败时抛出异常
     */
    public function validateLinkId(int $id): void
    {
        if ($id <= 0) {
            $validator = $this->validationFactory->make(['id' => $id], [
                'id' => 'required|integer|gt:0',
            ], [
                'id.gt' => '链接ID必须大于0',
            ]);
            throw new ValidationException($validator);
        }
    }

    /**
     * 验证脑图根节点ID.
     *
     * @param int $rootId 脑图根节点ID
     * @throws ValidationException 验证失败时抛出异常
     */
    public function validateRootId(int $rootId): void
    {
        if ($rootId <= 0) {
            $validator = $this->validationFactory->make(['root_id' => $rootId], [
                'root_id' => 'required|integer|gt:0',
            ], [
                'root_id.gt' => '脑图根节点ID必须大于0',
            ]);
            throw new ValidationException($validator);
        }
    }

    /**
     * 验证用户ID.
     *
     * @param int $userId 用户ID
     * @throws ValidationException 验证失败时抛出异常
     */
    public function validateUserId(int $userId): void
    {
        if ($userId <= 0) {
            $validator = $this->validationFactory->make(['user_id' => $userId], [
                'user_id' => 'required|integer|gt:0',
            ], [
                'user_id.gt' => '用户ID必须大于0',
            ]);
            throw new ValidationException($validator);
        }
    }
}
