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

namespace App\Service;

use App\Constants\ResponseMessage;
use App\Event\NewCommentEvent;
use App\Model\Comment;
use Exception;
use Hyperf\Context\ApplicationContext;
use App\Repository\CommentRepository;
use App\Repository\BlogRepository;
use App\Repository\CommentLikeRepository;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use Hyperf\Event\Contract\EventDispatcherInterface;

/**
 * 评论服务
 * 处理评论的增删改查功能.
 */
class CommentService
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var EventDispatcherInterface
     */
    protected $dispatcher;
    
    /**
     * @Inject
     * @var CommentLikeRepository
     */
    protected $commentLikeRepository;

    /**
     * @Inject
     * @var CommentRepository
     */
    protected $commentRepository;

    /**
     * @Inject
     * @var BlogRepository
     */
    protected $blogRepository;
    /**
     * 获取评论列表.
     *
     * @param array $params 查询参数
     * @param int|null $userId 当前用户ID（可选，用于判断是否点赞）
     * @return array{total:int, data:array, page:int, page_size:int} 评论列表和总数
     */
    public function getComments(array $params = [], ?int $userId = null): array
    {
        $this->logger->info('获取评论列表', ['params' => $params]);
        
        try {
            // 使用模型查询构建器，关联用户信息并只查询已发布的评论
            $query = Comment::with('user:id,username,avatar')
                ->approved();

            // 根据内容类型过滤
            if (isset($params['content_type'])) {
                $query->ofType($params['content_type']);
            }

            // 根据内容ID过滤
            if (isset($params['content_id'])) {
                $query->where('post_id', '=', $params['content_id']);
            }

            // 排序
            $query->orderBy('created_at', 'desc');

            // 分页
            $page = $params['page'] ?? 1;
            $pageSize = $params['page_size'] ?? 10;
            $offset = ($page - 1) * $pageSize;

            $total = $query->count();
            $comments = $query->offset($offset)->limit($pageSize)->get();
            
            // 转换为数组
            $commentData = $comments->toArray();
            
            // 添加点赞信息
            $this->enrichCommentsWithLikeData($commentData, $userId);

            $result = [
                'data' => $commentData,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
            ];
            
            $this->logger->info('获取评论列表成功', ['total' => $total]);
            return $result;
        } catch (Exception $e) {
            $this->logger->error('获取评论列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'data' => [],
                'total' => 0,
                'page' => $params['page'] ?? 1,
                'page_size' => $params['page_size'] ?? 10,
            ];
        }
    }
    
    /**
     * 为评论列表添加点赞数据
     *
     * @param array<string, mixed> $comments 评论列表
     * @param int|null $userId 当前用户ID
     */
    protected function enrichCommentsWithLikeData(array &$comments, ?int $userId = null): void
    {
        if (empty($comments)) {
            return;
        }

        // 获取评论ID列表
        $commentIds = array_column($comments, 'id');

        // 查询点赞数量
        $likeCountMap = $this->commentLikeRepository->getLikeCountsByCommentIds($commentIds);

        // 查询当前用户的点赞记录
        $userLikes = $userId ? $this->commentLikeRepository->getUserLikesByCommentIds($commentIds, $userId) : [];

        // 为评论添加点赞信息
        foreach ($comments as &$comment) {
            $commentId = $comment['id'];
            $comment['likes'] = $likeCountMap[$commentId] ?? 0;
            $comment['user_liked'] = isset($userLikes[$commentId]) ? true : false;
        }
    }

    /**
     * 点赞评论
     *
     * @param int $commentId 评论ID
     * @param int $userId 用户ID
     * @return bool 是否点赞成功
     * @throws Exception
     */
    public function likeComment(int $commentId, int $userId): bool
    {
        // 检查评论是否存在
        $comment = Comment::find($commentId);
        if (! $comment) {
            throw new Exception('评论不存在');
        }

        // 检查是否已经点赞
        $existingLike = Db::table('comment_likes')
            ->where('comment_id', $commentId)
            ->where('user_id', $userId)
            ->first();

        if ($existingLike) {
            return true; // 已经点赞过了
        }

        // 添加点赞记录
        Db::table('comment_likes')->insert([
            'comment_id' => $commentId,
            'user_id' => $userId,
            'created_at' => time(),
        ]);

        // 更新评论点赞数（可选，可以在查询时实时计算）
        try {
            Db::table('comments')
                ->where('id', $commentId)
                ->increment('like_count');
        } catch (\Exception $e) {
            $this->logger->error('更新评论点赞数失败', ['comment_id' => $commentId, 'error' => $e->getMessage()]);
        }

        $this->logger->info('评论点赞成功', ['comment_id' => $commentId, 'user_id' => $userId]);
        return true;
    }

    /**
     * 取消点赞评论
     *
     * @param int $commentId 评论ID
     * @param int $userId 用户ID
     * @return bool 是否取消成功
     */
    public function unlikeComment(int $commentId, int $userId): bool
    {
        // 删除点赞记录
        $result = Db::table('comment_likes')
            ->where('comment_id', $commentId)
            ->where('user_id', $userId)
            ->delete();

        if ($result) {
            // 更新评论点赞数（可选）
            try {
                Db::table('comments')
                    ->where('id', $commentId)
                    ->decrement('like_count', 1, 0); // 确保不会小于0
            } catch (\Exception $e) {
                $this->logger->error('更新评论点赞数失败', ['comment_id' => $commentId, 'error' => $e->getMessage()]);
            }

            $this->logger->info('取消评论点赞成功', ['comment_id' => $commentId, 'user_id' => $userId]);
        }

        return $result > 0;
    }

    /**
     * 创建评论.
     *
     * @param array{user_id:int, content:string, post_id?:int, post_type?:string, parent_id?:int, status?:int} $data 评论数据
     * @return int 评论ID
     * @throws Exception 当评论内容超长时抛出异常
     */
    public function createComment(array $data): int
    {
        $this->logger->info('创建评论', ['data' => $data]);
        
        try {
            // 验证评论内容长度（限制1000个中文字符）
            if (isset($data['content'])) {
                $this->validateContentLength($data['content']);
            }

            // 过滤不需要的字段
            $commentData = [
                'user_id' => $data['user_id'],
                'post_id' => $data['post_id'] ?? null,
                'post_type' => $data['post_type'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
                'content' => $data['content'],
                'status' => $data['status'] ?? Comment::STATUS_PENDING, // 默认为0（待审核）
            ];

            // 使用模型创建评论
            $comment = Comment::create($commentData);

            // 触发审核通知（可以在这里调用邮件服务通知管理员有新评论需要审核）
            $this->notifyNewCommentForReview($comment->id);
            
            $this->logger->info('创建评论成功', ['comment_id' => $comment->id]);
            return $comment->id;
        } catch (Exception $e) {
            $this->logger->error('创建评论失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 更新评论.
     *
     * @param int $id 评论ID
     * @param array{content?:string, status?:int} $data 评论数据
     * @return bool 更新是否成功
     * @throws Exception 当评论内容超长时抛出异常
     */
    public function updateComment(int $id, array $data): bool
    {
        // 验证评论内容长度（如果更新内容）
        if (isset($data['content'])) {
            $this->validateContentLength($data['content']);
        }

        // 过滤不需要的字段
        $commentData = [];
        if (isset($data['content'])) {
            $commentData['content'] = $data['content'];
        }
        if (isset($data['status'])) {
            $commentData['status'] = $data['status'];
        }

        // 更新评论数据
        return Db::table('comments')
            ->where('id', '=', $id)
            ->update($commentData) > 0;
    }

    /**
     * 审核通过评论.
     *
     * @param int $id 评论ID
     * @return bool 操作是否成功
     */
    public function approveComment(int $id): bool
    {
        return $this->updateCommentStatus($id, 1);
    }

    /**
     * 拒绝评论.
     *
     * @param int $id 评论ID
     * @return bool 操作是否成功
     */
    public function rejectComment(int $id): bool
    {
        return $this->updateCommentStatus($id, 2);
    }

    /**
     * 获取待审核评论列表.
     *
     * @param array $params 查询参数
     * @return array 评论列表
     */
    public function getPendingComments(array $params = []): array
    {
        $query = Db::table('comments')
            ->select(
                'comments.*',
                'users.username',
                'users.avatar',
                'blogs.title as blog_title',
                'works.title as work_title'
            )
            ->leftJoin('users', 'comments.user_id', '=', 'users.id')
            ->leftJoin('blogs', function ($join) {
                $join->on('comments.post_id', '=', 'blogs.id')
                    ->where('comments.post_type', '=', 'blog');
            })
            ->leftJoin('works', function ($join) {
                $join->on('comments.post_id', '=', 'works.id')
                    ->where('comments.post_type', '=', 'work');
            })
            ->where('comments.status', '=', 0);

        // 可选的类型筛选
        if (isset($params['post_type'])) {
            $query->where('comments.post_type', '=', $params['post_type']);
        }

        // 排序
        $query->orderBy('comments.created_at', 'desc');

        // 分页
        $page = $params['page'] ?? 1;
        $pageSize = $params['page_size'] ?? 20;
        $offset = ($page - 1) * $pageSize;

        $total = $query->count();
        $comments = $query->offset($offset)->limit($pageSize)->get();

        return [
            'data' => $comments,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 删除评论.
     *
     * @param int $id 评论ID
     * @return bool 删除结果
     */
    public function deleteComment(int $id): bool
    {
        $this->logger->info('删除评论', ['comment_id' => $id]);
        
        try {
            $comment = Comment::find($id);
            if (! $comment) {
                $this->logger->warning('评论不存在', ['comment_id' => $id]);
                return false;
            }
            
            $result = $comment->delete();
            
            if ($result) {
                $this->logger->info('删除评论成功', ['comment_id' => $id]);
            } else {
                $this->logger->warning('删除评论失败', ['comment_id' => $id]);
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error('删除评论异常', [
                'comment_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 获取评论详情.
     *
     * @param int $id 评论ID
     * @return array|null 评论数据
     */
    public function getCommentById(int $id): ?array
    {
        $this->logger->info('获取评论详情', ['comment_id' => $id]);
        
        try {
            $comment = Comment::with('user:id,username,avatar')
                ->find($id);

            $result = $comment ? $comment->toArray() : null;
            
            if ($result) {
                $this->logger->info('获取评论详情成功', ['comment_id' => $id]);
            } else {
                $this->logger->info('评论不存在', ['comment_id' => $id]);
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error('获取评论详情失败', [
                'comment_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * 回复评论.
     *
     * @param int $parentId 父评论ID
     * @param array{user_id:int, content:string, post_id?:int, post_type?:string} $data 回复数据
     * @return int 回复ID
     */
    public function replyComment(int $parentId, array $data): int
    {
        // 设置父评论ID
        $data['parent_id'] = $parentId;
        return $this->createComment($data);
    }

    /**
     * 获取评论的回复.
     *
     * @param int $parentId 父评论ID
     * @param array{include_pending?:bool, page?:int, page_size?:int} $params 查询参数
     * @param int|null $userId 当前用户ID（可选，用于判断是否点赞）
     * @return array{data:array, total:int, page:int, page_size:int} 回复列表
     */
    public function getReplies(int $parentId, array $params = [], ?int $userId = null): array
    {
        $query = Db::table('comments')
            ->select('comments.*', 'users.username', 'users.avatar')
            ->leftJoin('users', 'comments.user_id', '=', 'users.id')
            ->where('parent_id', '=', $parentId);

        // 非管理员查询时只返回已审核评论
        if (empty($params['include_pending']) || ! $params['include_pending']) {
            $query->where('status', '=', 1);
        }

        // 排序
        $query->orderBy('created_at', 'asc');

        // 分页
        $page = $params['page'] ?? 1;
        $pageSize = $params['page_size'] ?? 20;
        $offset = ($page - 1) * $pageSize;

        $total = $query->count();
        $replies = $query->offset($offset)->limit($pageSize)->get();
        
        // 转换为数组
        $replyData = $replies->toArray();
        
        // 添加点赞信息
        $this->enrichCommentsWithLikeData($replyData, $userId);

        return [
            'data' => $replyData,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 批量审核评论.
     *
     * @param array<int> $ids 评论ID数组
     * @param int $status 审核状态
     * @return array{total:int, success:int, failed:int, results:array<int,bool>} 审核结果
     */
    public function batchReviewComments(array $ids, int $status): array
    {
        $this->logger->info('批量审核评论', ['comment_ids' => $ids, 'status' => $status]);
        
        $success = 0;
        $failed = 0;
        $results = [];

        foreach ($ids as $id) {
            try {
                $result = $this->updateCommentStatus($id, $status);
                if ($result) {
                    ++$success;
                    $results[$id] = true;
                    $this->logger->info('评论审核成功', ['comment_id' => $id, 'status' => $status]);
                } else {
                    ++$failed;
                    $results[$id] = false;
                    $this->logger->warning('评论审核失败', ['comment_id' => $id]);
                }
            } catch (Exception $e) {
                ++$failed;
                $results[$id] = false;
                $this->logger->error('评论审核异常', [
                    'comment_id' => $id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $result = [
            'total' => count($ids),
            'success' => $success,
            'failed' => $failed,
            'results' => $results,
        ];
        
        $this->logger->info('批量审核评论完成', [
            'total' => count($ids),
            'success' => $success,
            'failed' => $failed
        ]);
        
        return $result;
    }

    /**
     * 验证评论内容长度.
     *
     * @param string $content 评论内容
     * @return void
     * @throws Exception 当内容超长时抛出异常
     */
    private function validateContentLength(string $content): void
    {
        // 计算中文字符数量（一个中文字符算1个，英文、数字、符号算半个）
        $chineseCount = preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $content) ?: 0;
        $nonChineseCount = mb_strlen((string)preg_replace('/[\x{4e00}-\x{9fa5}]/u', '', $content));
        $totalLength = $chineseCount + $nonChineseCount / 2;

        // 限制在1000个中文字符以内
        if ($totalLength > 1000) {
            throw new Exception(ResponseMessage::COMMENT_CONTENT_LENGTH_EXCEEDED);
        }
    }

    /**
     * 更新评论状态
     *
     * @param int $id 评论ID
     * @param int $status 新状态
     * @return bool 操作是否成功
     */
    private function updateCommentStatus(int $id, int $status): bool
    {
        // 检查评论是否存在
        $comment = $this->getCommentById($id);
        if (! $comment) {
            return false;
        }

        // 更新状态
        $result = Db::table('comments')
            ->where('id', '=', $id)
            ->update(['status' => $status]);

        if ($result && $status == 1) {
            // 如果是通过审核，更新相关内容的评论计数
            if (! empty($comment['post_id']) && ! empty($comment['post_type'])) {
                $this->updateCommentCount($comment['post_id'], $comment['post_type']);
            }
        }

        return $result > 0;
    }

    /**
     * 更新评论计数.
     *
     * @param int $postId 内容ID
     * @param string $postType 内容类型
     */
    private function updateCommentCount(int $postId, string $postType): void
    {
        try {
            // 计算该内容的已审核评论数
            $commentCount = $this->commentRepository->countApprovedByPost($postId, $postType);

            // 根据内容类型更新对应表的评论计数
            if ($postType === 'blog') {
                $this->blogRepository->updateCommentCount($postId, $commentCount);
            } else if ($postType === 'works') {
                // 如果有WorkRepository，使用它更新评论计数
                // $this->workRepository->updateCommentCount($postId, $commentCount);
                $this->logger->info('更新作品评论计数', ['post_id' => $postId, 'count' => $commentCount]);
            }
        } catch (\Exception $e) {
            $this->logger->error('更新评论计数失败', ['post_id' => $postId, 'post_type' => $postType, 'error' => $e->getMessage()]);
        }
    }

    /**
     * 通知新评论需要审核.
     *
     * @param int $commentId 评论ID
     */
    private function notifyNewCommentForReview(int $commentId): void
    {
        $this->logger->info('通知新评论需要审核', ['comment_id' => $commentId]);
        
        try {
            // 获取评论数据
            $commentData = $this->getCommentById($commentId);

            // 使用依赖注入的事件分发器触发事件
            $this->dispatcher->dispatch(new NewCommentEvent($commentId, $commentData ?? []));
            
            $this->logger->info('新评论审核通知触发成功', ['comment_id' => $commentId]);
        } catch (Exception $e) {
            $this->logger->error('触发新评论审核通知失败', [
                'comment_id' => $commentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
