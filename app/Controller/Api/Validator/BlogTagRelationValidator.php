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
 * 博客标签关系验证器
 * 处理博客与标签关联相关的请求参数验证
 */
class BlogTagRelationValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /**
     * 验证添加标签的请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证通过的数据
     * @throws ValidationException
     */
    public function validateAddTags(array $data): array
    {
        $validator = $this->validationFactory->make($data, [
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'required|integer|min:1',
        ], [
            'tag_ids.required' => '标签ID列表不能为空',
            'tag_ids.array' => '标签ID必须是数组格式',
            'tag_ids.*.required' => '每个标签ID不能为空',
            'tag_ids.*.integer' => '标签ID必须是整数',
            'tag_ids.*.min' => '标签ID必须大于0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证更新标签的请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证通过的数据
     * @throws ValidationException
     */
    public function validateUpdateTags(array $data): array
    {
        return $this->validateAddTags($data);
    }

    /**
     * 验证移除标签的请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证通过的数据
     * @throws ValidationException
     */
    public function validateRemoveTags(array $data): array
    {
        return $this->validateAddTags($data);
    }

    /**
     * 验证博客ID.
     *
     * @param int $blogId 博客ID
     * @return bool 验证通过返回true
     * @throws ValidationException
     */
    public function validateBlogId(int $blogId): bool
    {
        $validator = $this->validationFactory->make(['blog_id' => $blogId], [
            'blog_id' => 'required|integer|min:1',
        ], [
            'blog_id.required' => '博客ID不能为空',
            'blog_id.integer' => '博客ID必须是整数',
            'blog_id.min' => '博客ID必须大于0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return true;
    }

    /**
     * 验证标签ID.
     *
     * @param int $tagId 标签ID
     * @return bool 验证通过返回true
     * @throws ValidationException
     */
    public function validateTagId(int $tagId): bool
    {
        $validator = $this->validationFactory->make(['tag_id' => $tagId], [
            'tag_id' => 'required|integer|min:1',
        ], [
            'tag_id.required' => '标签ID不能为空',
            'tag_id.integer' => '标签ID必须是整数',
            'tag_id.min' => '标签ID必须大于0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return true;
    }
}
