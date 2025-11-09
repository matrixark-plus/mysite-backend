<?php

declare(strict_types=1);
/**
 * 评论点赞控制器
 * 处理评论点赞相关功能
 */

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Controller\Api\Validator\CommentLikeValidator;
use App\Constants\ResponseMessage;
use App\Constants\StatusCode;
use App\Model\CommentLike;
use App\Service\CommentLikeService;
use App\Traits\LogTrait;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\RequestMethod;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Validation\ValidationException;

/**
 * @Controller(prefix="api/comments")
 */
class CommentLikeController extends AbstractController
{
    use LogTrait;
    
    /**
     * @Inject
     * @var CommentLikeService
     */
    protected $commentLikeService;
    
    /**
     * @Inject
     * @var CommentLikeValidator
     */
    protected $commentLikeValidator;

    /**
     * 点赞评论
     * @param int $id 评论ID
     */
    /**
     * @RequestMapping(path="{id}/like", methods={"POST"})
     */
    public function like(int $id)
    {
        try {
            // 验证评论ID并检查评论是否存在
            $this->commentLikeValidator->validateCommentId($id);

            // 获取当前用户
            $user = $this->user ?? null;
            if (! $user) {
                return $this->fail(StatusCode::UNAUTHORIZED, ResponseMessage::USER_NOT_LOGIN);
            }

            // 处理点赞
            $result = $this->commentLikeService->likeComment($id, $user->id);
            
            return $this->success(
                [
                    'liked' => $result['liked'],
                    'like_count' => $result['like_count'],
                    'message' => $result['message']
                ],
                $result['liked'] ? '点赞成功' : '取消点赞成功'
            );
        } catch (ValidationException $e) {
            return $this->fail(StatusCode::VALIDATION_ERROR, $e->validator->errors()->first());
        } catch (\Throwable $exception) {
            $this->logError('评论点赞失败', ['comment_id' => $id, 'error' => $exception->getMessage()], $exception);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '操作失败');
        }
    }

    /**
     * 获取评论点赞状态
     * @param int $id 评论ID
     */
    /**
     * @RequestMapping(path="{id}/like-status", methods={"GET"})
     */
    public function getLikeStatus(int $id)
    {
        try {
            // 验证评论ID并检查评论是否存在
            $this->commentLikeValidator->validateCommentId($id);

            // 获取当前用户
            $user = $this->user ?? null;
            
            // 获取点赞状态
            $isLiked = false;
            if ($user) {
                $isLiked = CommentLike::isLiked($id, $user->id);
            }
            
            // 获取点赞数
            $likeCount = CommentLike::getLikeCount($id);

            return $this->success([
                'is_liked' => $isLiked,
                'like_count' => $likeCount
            ]);
        } catch (ValidationException $e) {
            return $this->fail(StatusCode::VALIDATION_ERROR, $e->validator->errors()->first());
        } catch (\Throwable $exception) {
            $this->logError('获取评论点赞状态失败', ['comment_id' => $id, 'error' => $exception->getMessage()], $exception);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取失败');
        }
    }
}