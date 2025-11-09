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

namespace App\Controller\Api;

use App\Constants\ResponseMessage;
use App\Constants\StatusCode;
use App\Controller\AbstractController;
use App\Middleware\CorsMiddleware;
use App\Middleware\JwtAuthMiddleware;
use App\Service\CommentService;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

/**
 * 评论控制器.
 * @Controller(prefix="/api/comments")
 * @Middlewares({@Middleware(CorsMiddleware::class)})
 */
class CommentController extends AbstractController
{
    /**
     * @Inject
     * @var CommentService
     */
    protected $commentService;

    /**
     * 获取评论列表.
     * @RequestMapping(path="", methods={"GET"})
     */
    public function index(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $params = $request->all();
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 10);

            // 获取当前用户ID（如果已登录）
            $userId = null;
            $user = $this->request->getAttribute('user');
            if ($user) {
                $userId = $user->id;
            }

            // 获取评论列表
            $result = $this->commentService->getComments($params, $page, $pageSize, $userId);
            return $this->success($result, ResponseMessage::COMMENT_LIST_SUCCESS, StatusCode::SUCCESS);
        } catch (Exception $e) {
            return $this->serverError(ResponseMessage::COMMENT_LIST_FAILED . ': ' . $e->message);
        }
    }

    /**
     * 创建评论.
     * @RequestMapping(path="", methods={"POST"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function store(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $data = $request->all();
            // 验证必要字段
            if (empty($data['post_id']) || empty($data['post_type']) || empty($data['content'])) {
                return $this->validationError(ResponseMessage::COMMENT_PARAM_REQUIRED);
            }

            // 添加当前用户ID
            $user = $this->request->getAttribute('user');
            $data['user_id'] = $user->id;
            if (! $data['user_id']) {
                return $this->unauthorized('用户未登录');
            }

            // 创建评论
            $commentId = $this->commentService->createComment($data);

            // 获取创建的评论详情
            $comment = $this->commentService->getCommentById($commentId);

            return $this->success($comment, ResponseMessage::COMMENT_CREATE_SUCCESS, StatusCode::SUCCESS);
        } catch (Exception $e) {
            return $this->serverError(ResponseMessage::COMMENT_CREATE_FAILED . ': ' . $e->getMessage());
        }
    }

    /**
     * 获取评论详情.
     * @RequestMapping(path="/{id}", methods={"GET"})
     */
    public function show(int $id, RequestInterface $request, ResponseInterface $response)
    {
        try {
            $comment = $this->commentService->getCommentById($id);
            if (! $comment) {
                return $this->notFound('评论不存在');
            }

            // 普通用户只能查看已审核通过的评论
            $isAdmin = $this->request->getAttribute('is_admin') ?? false;
            if (! $isAdmin && $comment['status'] != 1) {
                return $this->fail(StatusCode::FORBIDDEN, '无权查看该评论');
            }

            // 添加点赞信息
            $userId = null;
            $user = $this->request->getAttribute('user');
            if ($user) {
                $userId = $user->id;
            }

            // 查询点赞数和用户点赞状态
            $comment = $this->commentService->enhanceCommentWithLikes($comment, $userId);

            return $this->success($comment, '获取评论详情成功', StatusCode::SUCCESS);
        } catch (Exception $e) {
            return $this->serverError(ResponseMessage::COMMENT_SHOW_FAILED . ': ' . $e->getMessage());
        }
    }

    /**
     * 更新评论.
     * @RequestMapping(path="/{id}", methods={"PUT"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function update(int $id, RequestInterface $request, ResponseInterface $response)
    {
        try {
            $comment = $this->commentService->getCommentById($id);
            if (! $comment) {
                return $this->notFound('评论不存在');
            }

            // 检查权限：只有评论作者或管理员可以更新评论
            $user = $this->request->getAttribute('user');
            $userId = $user->id;
            $isAdmin = $this->request->getAttribute('is_admin') ?? false;
            if ($comment['user_id'] != $userId && ! $isAdmin) {
                return $this->fail(StatusCode::FORBIDDEN, '无权更新该评论');
            }

            $data = $request->all();
            $result = $this->commentService->updateComment($id, $data);

            if ($result) {
                return $this->success(null, ResponseMessage::COMMENT_UPDATE_SUCCESS, StatusCode::SUCCESS);
            }
            return $this->serverError('评论更新失败');
        } catch (Exception $e) {
            return $this->serverError('更新评论失败: ' . $e->getMessage());
        }
    }

    /**
     * 删除评论.
     * @RequestMapping(path="/{id}", methods={"DELETE"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function destroy(int $id, ResponseInterface $response)
    {
        try {
            $comment = $this->commentService->getCommentById($id);
            if (! $comment) {
                return $this->notFound('评论不存在');
            }

            // 检查权限：只有评论作者或管理员可以删除评论
            $user = $this->request->getAttribute('user');
            $userId = $user->id;
            $isAdmin = $this->request->getAttribute('is_admin') ?? false;
            if ($comment['user_id'] != $userId && ! $isAdmin) {
                return $this->fail(StatusCode::FORBIDDEN, '无权删除该评论');
            }

            $result = $this->commentService->deleteComment($id);

            if ($result) {
                return $this->success(null, '评论删除成功', StatusCode::SUCCESS);
            }
            return $this->serverError(ResponseMessage::COMMENT_DELETE_FAILED);
        } catch (Exception $e) {
            return $this->serverError(ResponseMessage::COMMENT_DELETE_FAILED . ': ' . $e->getMessage());
        }
    }

    /**
     * 获取待审核评论列表（管理员功能）.
     * @RequestMapping(path="/pending/list", methods={"GET"})
     * @Middleware({JwtAuthMiddleware::class, "admin"})
     */
    public function getPendingComments(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $params = $request->all();
            $result = $this->commentService->getPendingComments($params);
            return $this->success($result, ResponseMessage::PENDING_COMMENTS_SUCCESS, StatusCode::SUCCESS);
        } catch (Exception $e) {
            return $this->serverError('获取待审核评论列表失败: ' . $e->getMessage());
        }
    }

    /**
     * 审核通过评论（管理员功能）.
     * @RequestMapping(path="/{id}/approve", methods={"PUT"})
     * @Middleware({JwtAuthMiddleware::class, "admin"})
     */
    public function approveComment(int $id, ResponseInterface $response)
    {
        try {
            $result = $this->commentService->approveComment($id);
            if ($result) {
                return $this->success(null, ResponseMessage::COMMENT_APPROVE_SUCCESS, StatusCode::SUCCESS);
            }
            return $this->notFound(ResponseMessage::COMMENT_APPROVE_FAILED);
        } catch (Exception $e) {
            return $this->serverError(ResponseMessage::COMMENT_APPROVE_ERROR . ': ' . $e->getMessage());
        }
    }

    /**
     * 拒绝评论（管理员功能）.
     * @RequestMapping(path="/{id}/reject", methods={"PUT"})
     * @Middleware({JwtAuthMiddleware::class, "admin"})
     */
    public function rejectComment(int $id, ResponseInterface $response)
    {
        try {
            $result = $this->commentService->rejectComment($id);
            if ($result) {
                return $this->success(null, ResponseMessage::COMMENT_REJECT_SUCCESS, StatusCode::SUCCESS);
            }
            return $this->notFound(ResponseMessage::COMMENT_REJECT_FAILED);
        } catch (Exception $e) {
            return $this->serverError(ResponseMessage::COMMENT_REJECT_ERROR . ': ' . $e->getMessage());
        }
    }

    /**
     * 批量审核评论（管理员功能）.
     * @RequestMapping(path="/batch-review", methods={"POST"})
     * @Middleware({JwtAuthMiddleware::class, "admin"})
     */
    public function batchReviewComments(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $data = $request->all();

            // 验证参数
            if (empty($data['ids']) || ! is_array($data['ids']) || ! isset($data['status'])) {
                return $this->validationError(ResponseMessage::COMMENT_BATCH_PARAM_REQUIRED);
            }

            // 验证状态值
            if (! in_array($data['status'], [1, 2])) {
                return $this->validationError(ResponseMessage::COMMENT_STATUS_INVALID);
            }

            $result = $this->commentService->batchReviewComments($data['ids'], $data['status']);

            return $this->success($result, ResponseMessage::COMMENT_BATCH_REVIEW_SUCCESS, StatusCode::SUCCESS);
        } catch (Exception $e) {
            return $this->serverError(ResponseMessage::COMMENT_BATCH_REVIEW_FAILED . ': ' . $e->getMessage());
        }
    }

    /**
     * 获取评论的回复.
     * @RequestMapping(path="/{id}/replies", methods={"GET"})
     */
    public function getReplies(int $id, RequestInterface $request, ResponseInterface $response)
    {
        try {
            $params = $request->all();
            $page = $request->input('page', 1);
            $pageSize = $request->input('page_size', 10);

            // 管理员可以查看所有回复（包括待审核的）
            $isAdmin = $this->request->getAttribute('is_admin') ?? false;
            if ($isAdmin) {
                $params['include_pending'] = true;
            }

            // 获取当前用户ID（如果已登录）
            $userId = null;
            $user = $this->request->getAttribute('user');
            if ($user) {
                $userId = $user->id;
            }

            $result = $this->commentService->getReplies($id, $params, $page, $pageSize, $userId);
            return $this->success($result, ResponseMessage::COMMENT_REPLIES_SUCCESS, StatusCode::SUCCESS);
        } catch (Exception $e) {
            return $this->serverError(ResponseMessage::COMMENT_REPLIES_FAILED . ': ' . $e->getMessage());
        }
    }

    /**
     * 点赞评论.
     * @RequestMapping(path="/{id}/like", methods={"POST"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function likeComment(int $id, ResponseInterface $response)
    {
        try {
            // 获取当前用户ID
            $user = $this->request->getAttribute('user');
            if (! $user) {
                return $this->unauthorized('用户未登录');
            }
            $userId = $user->id;

            // 点赞评论
            $this->commentService->likeComment($id, $userId);

            return $this->success(null, '点赞成功', StatusCode::SUCCESS);
        } catch (Exception $e) {
            return $this->serverError('点赞失败: ' . $e->getMessage());
        }
    }

    /**
     * 取消点赞评论.
     * @RequestMapping(path="/{id}/like", methods={"DELETE"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function unlikeComment(int $id, ResponseInterface $response)
    {
        try {
            // 获取当前用户ID
            $user = $this->request->getAttribute('user');
            if (! $user) {
                return $this->unauthorized('用户未登录');
            }
            $userId = $user->id;

            // 取消点赞
            $this->commentService->unlikeComment($id, $userId);

            return $this->success(null, '取消点赞成功', StatusCode::SUCCESS);
        } catch (Exception $e) {
            return $this->serverError('取消点赞失败: ' . $e->getMessage());
        }
    }

    /**
     * 回复评论.
     * @RequestMapping(path="/{id}/reply", methods={"POST"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function replyComment(int $id, RequestInterface $request, ResponseInterface $response)
    {
        try {
            $data = $request->all();

            // 验证内容
            if (empty($data['content'])) {
                return $this->validationError(ResponseMessage::COMMENT_REPLY_CONTENT_REQUIRED);
            }

            // 添加当前用户ID
            $user = $this->request->getAttribute('user');
            $data['user_id'] = $user->id;
            if (! $data['user_id']) {
                return $this->unauthorized('用户未登录');
            }

            // 创建回复
            $replyId = $this->commentService->replyComment($id, $data);

            // 获取创建的回复详情
            $reply = $this->commentService->getCommentById($replyId);

            return $this->success($reply, ResponseMessage::COMMENT_REPLY_CREATE_SUCCESS, StatusCode::SUCCESS);
        } catch (Exception $e) {
            return $this->serverError(ResponseMessage::COMMENT_REPLY_CREATE_FAILED . ': ' . $e->getMessage());
        }
    }
}
