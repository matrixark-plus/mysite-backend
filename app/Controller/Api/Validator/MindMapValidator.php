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
 * 脑图验证器
 * 处理脑图相关的请求参数验证
 */
class MindMapValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /**
     * 验证根节点列表请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证通过的数据
     * @throws ValidationException
     */
    public function validateRootNodes(array $data): array
    {
        $validator = $this->validationFactory->make($data, [
            'page' => 'nullable|integer|min:1',
            'page_size' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string',
            'order' => 'nullable|in:asc,desc',
        ], [
            'page.integer' => '页码必须是整数',
            'page.min' => '页码必须大于0',
            'page_size.integer' => '每页数量必须是整数',
            'page_size.min' => '每页数量必须大于0',
            'page_size.max' => '每页数量不能超过100',
            'order.in' => '排序方向必须是asc或desc',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证脑图ID.
     *
     * @param int $id 脑图ID
     * @return bool 验证通过返回true
     * @throws ValidationException
     */
    public function validateMindMapId(int $id): bool
    {
        $validator = $this->validationFactory->make(['id' => $id], [
            'id' => 'required|integer|min:1',
        ], [
            'id.required' => '脑图ID不能为空',
            'id.integer' => '脑图ID必须是整数',
            'id.min' => '脑图ID必须大于0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return true;
    }
}
