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
 * 评论管理相关的参数验证器.
 */
class CommentValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validatorFactory;

    /**
     * 验证评论列表请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateCommentList(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'post_id' => 'sometimes|integer|min:1',
            'post_type' => 'sometimes|string|in:blog,work,note',
            'page' => 'sometimes|integer|min:1',
            'page_size' => 'sometimes|integer|min:1|max:100',
            'parent_id' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证创建评论请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateCreateComment(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'post_id' => 'required|integer|min:1',
            'post_type' => 'required|string|in:blog,work,note',
            'content' => 'required|string|min:1|max:1000',
            'parent_id' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * 验证更新评论请求参数.
     *
     * @param array $data 请求数据
     * @return array 验证后的数据
     * @throws ValidationException 当验证失败时抛出异常
     */
    public function validateUpdateComment(array $data): array
    {
        $validator = $this->validatorFactory->make($data, [
            'content' => 'required|string|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
