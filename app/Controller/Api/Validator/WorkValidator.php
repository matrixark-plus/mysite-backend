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
 * 作品管理相关的参数验证器.
 */
class WorkValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validatorFactory;

    /**
     * 验证作品列表请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateWorkList(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'category_id' => 'sometimes|integer|min:1',
            'keyword' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证创建作品请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateCreateWork(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'title' => 'required|string|max:255',
            'description' => 'sometimes|string',
            'category_id' => 'required|integer|min:1',
            'cover_image' => 'sometimes|string|max:500',
            'images' => 'sometimes|array',
            'demo_url' => 'sometimes|url|max:500',
            'source_url' => 'sometimes|url|max:500',
            'is_public' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证更新作品请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateUpdateWork(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category_id' => 'sometimes|integer|min:1',
            'cover_image' => 'sometimes|string|max:500',
            'images' => 'sometimes|array',
            'demo_url' => 'sometimes|url|max:500',
            'source_url' => 'sometimes|url|max:500',
            'is_public' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证创建分类请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateCreateCategory(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'name' => 'required|string|max:100|unique:work_categories,name',
            'description' => 'sometimes|string|max:500',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证更新分类请求参数.
     *
     * @param array $data 请求数据
     * @param int $categoryId 分类ID
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateUpdateCategory(array $data, int $categoryId): array
    {
        $validator = $this->validatorFactory->make($data, [
            'name' => "sometimes|string|max:100|unique:work_categories,name,{$categoryId}",
            'description' => 'sometimes|string|max:500',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
