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
 * 博客管理相关的参数验证器.
 */
class BlogValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validatorFactory;

    /**
     * 验证博客列表请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateBlogList(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'category_id' => 'sometimes|integer|min:1',
            'keyword' => 'sometimes|string|max:255',
            'order' => 'sometimes|string|in:newest,oldest,popular',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证创建博客请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateCreateBlog(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category_id' => 'required|integer|min:1',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:50',
            'cover_image' => 'sometimes|string|max:500',
            'is_published' => 'sometimes|boolean',
            'excerpt' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证更新博客请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateUpdateBlog(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'category_id' => 'sometimes|integer|min:1',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:50',
            'cover_image' => 'sometimes|string|max:500',
            'is_published' => 'sometimes|boolean',
            'excerpt' => 'sometimes|string|max:500',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
