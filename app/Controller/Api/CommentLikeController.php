<?php

declare(strict_types=1);
/**
 * 评论点赞控制器
 * 处理评论点赞相关功能
 */

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Constants\ResponseMessage;
use App\Constants\StatusCode;
use App\Model\Comment;
use App\Model\CommentLike;
use App\Service\CommentLikeService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\RequestMethod;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Di\Annotation\Inject;

/**
 * @Controller(prefix="api/comments")
 */
class CommentLikeController extends AbstractController
{
    /**
     * @Inject
     * @var CommentLikeService
     */
    protected $commentLikeService;

    /**
     * 点赞评论
     * @param int $id 评论ID
     * @return ResponseInterface
     */
    /**
     * @RequestMapping(path="{id}/like", methods={"POST"})
     */
    public function like(int $id): ResponseInterface
    {
        try {
            // 验证评论是否存在
            $comment = Comment::find($id);
            if (! $comment) {
                return $this->fail(StatusCode::NOT_FOUND, '评论不存在');
            }

            // 获取当前用户
            $user = $this->getCurrentUser();
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
        } catch (\Throwable $exception) {
            $this->logError('评论点赞失败', ['comment_id' => $id, 'error' => $exception->getMessage()], $exception);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '操作失败');
        }
    }

    /**
     * 获取评论点赞状态
     * @param int $id 评论ID
     * @return ResponseInterface
     */
    /**
     * @RequestMapping(path="{id}/like-status", methods={"GET"})
     */
    public function getLikeStatus(int $id): ResponseInterface
    {
        try {
            // 验证评论是否存在
            $comment = Comment::find($id);
            if (! $comment) {
                return $this->fail(StatusCode::NOT_FOUND, '评论不存在');
            }

            // 获取当前用户
            $user = $this->getCurrentUser();
            
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
        } catch (\Throwable $exception) {
            $this->logError('获取评论点赞状态失败', ['comment_id' => $id, 'error' => $exception->getMessage()], $exception);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取失败');
        }
    }
}