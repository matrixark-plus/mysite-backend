<?php

declare(strict_types=1);

namespace App\Controller\Api\Validator;

use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Validation\ValidationException;
use App\Model\Comment;
use App\Constants\StatusCode;

/**
 * 评论点赞验证器
 * 处理评论点赞相关的请求参数验证
 */
class CommentLikeValidator
{
    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    /**
     * 验证评论ID并检查评论是否存在
     *
     * @param int $commentId 评论ID
     * @return bool 验证通过返回true
     * @throws ValidationException
     */
    public function validateCommentId(int $commentId): bool
    {
        // 首先验证评论ID格式
        $validator = $this->validationFactory->make(['comment_id' => $commentId], [
            'comment_id' => 'required|integer|min:1',
        ], [
            'comment_id.required' => '评论ID不能为空',
            'comment_id.integer' => '评论ID必须是整数',
            'comment_id.min' => '评论ID必须大于0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // 检查评论是否存在
        $comment = Comment::find($commentId);
        if (! $comment) {
            $validator = $this->validationFactory->make([], []);
            $validator->errors()->add('comment_id', '评论不存在');
            throw new ValidationException($validator);
        }

        return true;
    }
}