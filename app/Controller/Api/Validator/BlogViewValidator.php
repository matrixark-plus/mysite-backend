<?php

declare(strict_types=1);

namespace App\Controller\Api\Validator;

use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Validation\ValidationException;

/**
 * 博客阅读量验证器
 * 处理博客阅读量记录相关的请求参数验证
 */
class BlogViewValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /**
     * 验证博客ID
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
}