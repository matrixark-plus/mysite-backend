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
 * 处理思维导图节点链接相关的请求参数验证
 */
class NodeLinkValidator
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
    public function validateCreateNodeLink(array $data): void
    {
        $validator = $this->validationFactory->make($data, [
            'source_node_id' => 'required|integer|gt:0',
            'target_node_id' => 'required|integer|gt:0',
            'mindmap_id' => 'required|integer|gt:0',
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
            'mindmap_id.required' => '思维导图ID不能为空',
            'mindmap_id.integer' => '思维导图ID必须是整数',
            'mindmap_id.gt' => '思维导图ID必须大于0',
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
     * @param array $links 链接数据数组
     * @throws ValidationException 验证失败时抛出异常
     */
    public function validateBatchCreateNodeLinks(array $links): void
    {
        if (empty($links)) {
            $validator = $this->validationFactory->make(['links' => $links], [
                'links' => 'required|array',
            ], [
                'links.required' => '链接数据数组不能为空',
            ]);
            throw new ValidationException($validator);
        }

        if (count($links) > 100) {
            $validator = $this->validationFactory->make(['links' => $links], [
                'links' => 'max:100',
            ], [
                'links.max' => '批量创建的链接数量不能超过100条',
            ]);
            throw new ValidationException($validator);
        }

        // 验证每条链接数据
        foreach ($links as $index => $link) {
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
                'mindmap_id' => 'required|integer|gt:0',
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
     * 验证思维导图ID.
     *
     * @param int $mindmapId 思维导图ID
     * @throws ValidationException 验证失败时抛出异常
     */
    public function validateMindmapId(int $mindmapId): void
    {
        if ($mindmapId <= 0) {
            $validator = $this->validationFactory->make(['mindmap_id' => $mindmapId], [
                'mindmap_id' => 'required|integer|gt:0',
            ], [
                'mindmap_id.gt' => '思维导图ID必须大于0',
            ]);
            throw new ValidationException($validator);
        }
    }

    /**
     * 验证链接ID.
     *
     * @param int $linkId 链接ID
     * @throws ValidationException 验证失败时抛出异常
     */
    public function validateLinkId(int $linkId): void
    {
        if ($linkId <= 0) {
            $validator = $this->validationFactory->make(['link_id' => $linkId], [
                'link_id' => 'required|integer|gt:0',
            ], [
                'link_id.gt' => '链接ID必须大于0',
            ]);
            throw new ValidationException($validator);
        }
    }

    /**
     * 验证用户ID.
     *
     * @param null|int $userId 用户ID
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
