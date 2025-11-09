<?php

declare(strict_types=1);

namespace App\Controller\Api\Validator;

use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Validation\ValidationException;

/**
 * 思维导图节点验证器
 * 处理思维导图节点相关的请求参数验证
 */
class MindmapNodeValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /**
     * 验证创建节点的请求参数
     *
     * @param array $data 请求数据
     * @return array 验证通过的数据
     * @throws ValidationException
     */
    public function validateCreateNode(array $data): array
    {
        $validator = $this->validationFactory->make($data, [
            'parent_id' => 'nullable|integer|min:1',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'position' => 'nullable|array',
            'style' => 'nullable|array',
            'root_id' => 'required|integer|min:1',
        ], [
            'title.required' => '节点标题不能为空',
            'title.string' => '节点标题必须是字符串',
            'title.max' => '节点标题不能超过255个字符',
            'parent_id.integer' => '父节点ID必须是整数',
            'parent_id.min' => '父节点ID必须大于0',
            'root_id.required' => '思维导图ID不能为空',
            'root_id.integer' => '思维导图ID必须是整数',
            'root_id.min' => '思维导图ID必须大于0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证批量创建节点的请求参数
     *
     * @param array $data 请求数据
     * @return array 验证通过的数据
     * @throws ValidationException
     */
    public function validateBatchCreateNodes(array $data): array
    {
        $validator = $this->validationFactory->make($data, [
            'nodes' => 'required|array',
            'nodes.*.parent_id' => 'nullable|integer|min:1',
            'nodes.*.title' => 'required|string|max:255',
            'nodes.*.content' => 'nullable|string',
            'nodes.*.position' => 'nullable|array',
            'nodes.*.style' => 'nullable|array',
            'nodes.*.root_id' => 'required|integer|min:1',
        ], [
            'nodes.required' => '节点数据不能为空',
            'nodes.array' => '节点数据必须是数组格式',
            'nodes.*.title.required' => '每个节点标题不能为空',
            'nodes.*.title.string' => '节点标题必须是字符串',
            'nodes.*.title.max' => '节点标题不能超过255个字符',
            'nodes.*.parent_id.integer' => '父节点ID必须是整数',
            'nodes.*.parent_id.min' => '父节点ID必须大于0',
            'nodes.*.root_id.required' => '思维导图ID不能为空',
            'nodes.*.root_id.integer' => '思维导图ID必须是整数',
            'nodes.*.root_id.min' => '思维导图ID必须大于0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证更新节点的请求参数
     *
     * @param array $data 请求数据
     * @return array 验证通过的数据
     * @throws ValidationException
     */
    public function validateUpdateNode(array $data): array
    {
        $validator = $this->validationFactory->make($data, [
            'parent_id' => 'nullable|integer|min:1',
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'position' => 'nullable|array',
            'style' => 'nullable|array',
        ], [
            'title.string' => '节点标题必须是字符串',
            'title.max' => '节点标题不能超过255个字符',
            'parent_id.integer' => '父节点ID必须是整数',
            'parent_id.min' => '父节点ID必须大于0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证思维导图ID
     *
     * @param int $mindmapId 思维导图ID
     * @return bool 验证通过返回true
     * @throws ValidationException
     */
    public function validateMindmapId(int $mindmapId): bool
    {
        $validator = $this->validationFactory->make(['mindmap_id' => $mindmapId], [
            'mindmap_id' => 'required|integer|min:1',
        ], [
            'mindmap_id.required' => '思维导图ID不能为空',
            'mindmap_id.integer' => '思维导图ID必须是整数',
            'mindmap_id.min' => '思维导图ID必须大于0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return true;
    }

    /**
     * 验证节点ID
     *
     * @param int $nodeId 节点ID
     * @return bool 验证通过返回true
     * @throws ValidationException
     */
    public function validateNodeId(int $nodeId): bool
    {
        $validator = $this->validationFactory->make(['node_id' => $nodeId], [
            'node_id' => 'required|integer|min:1',
        ], [
            'node_id.required' => '节点ID不能为空',
            'node_id.integer' => '节点ID必须是整数',
            'node_id.min' => '节点ID必须大于0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return true;
    }
}