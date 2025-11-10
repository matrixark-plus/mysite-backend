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
use App\Repository\BlogRepository;
use App\Repository\CommentLikeRepository;
use App\Repository\CommentRepository;
use Exception;
use Hyperf\Cache\Cache;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Event\Contract\EventDispatcher;
use Hyperf\DbConnection\Db;

/**
 * 评论服务
 * 处理评论的增删改查功能.
 */
class CommentService extends BaseService
{


    /**
     * @Inject
     * @var EventDispatcher
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
     * @Inject
     * @var Cache
     */
    protected $cache;

    /**
     * 获取评论列表.
     *
     * @param array $params 查询参数
     * @param null|int $userId 当前用户ID（可选，用于判断是否点赞）
     * @return array{total:int, data:array, page:int, page_size:int} 评论列表和总数
     */
    public function getComments(array $params = [], ?int $userId = null): array
    {
        return $this->executeWithErrorHandling(
            function () use ($params, $userId) {
                $this->logAction('获取评论列表', ['params' => $params]);
                
                // 构建缓存键 - 只有匿名用户的查询才缓存
                $cacheKey = null;
                if (! $userId) {
                    $cacheKey = 'comments:list:' . md5(json_encode([
                        'content_type' => $params['content_type'] ?? '',
                        'content_id' => $params['content_id'] ?? '',
                        'page' => $params['page'] ?? 1,
                        'page_size' => $params['page_size'] ?? 10,
                    ]));

                    // 尝试从缓存获取
                    $cachedResult = $this->cache->get($cacheKey);
                    if ($cachedResult) {
                        return $cachedResult;
                    }
                }

                // 使用Repository获取评论列表，统一数据访问层
                $result = $this->commentRepository->findWithPagination($params);

                // 添加点赞信息
                $this->enrichCommentsWithLikeData($result['data'], $userId);

                // 缓存非个性化结果
                if ($cacheKey) {
                    $this->cache->set($cacheKey, $result, 300); // 5分钟缓存
                }

                $this->logAction('获取评论列表成功', ['total' => $result['total']]);
                return $result;
            },
            '获取评论列表失败',
            ['params' => $params]
        )['data'] ?? [
            'data' => [],
            'total' => 0,
            'page' => $params['page'] ?? 1,
            'page_size' => $params['page_size'] ?? 10,
        ];
    }

    /**
     * 点赞评论.
     *
     * @param int $commentId 评论ID
     * @param int $userId 用户ID
     * @return bool 是否点赞成功
     */
    public function likeComment(int $commentId, int $userId): bool
    {
        return $this->executeWithErrorHandling(
            function () use ($commentId, $userId) {
                // 检查评论是否存在
                $comment = $this->commentRepository->findById($commentId);
                if (! $comment) {
                    throw new Exception('评论不存在');
                }

                // 检查是否已经点赞
                $existingLike = $this->commentLikeRepository->findByCommentAndUser($commentId, $userId);
                if ($existingLike) {
                    return true; // 已经点赞过了
                }

                // 添加点赞记录
                $this->commentLikeRepository->create([
                    'comment_id' => $commentId,
                    'user_id' => $userId,
                    'created_at' => time(),
                ]);

                // 更新评论点赞数（可选，可以在查询时实时计算）
                try {
                    $this->commentRepository->update($commentId, ['like_count' => ($comment['like_count'] ?? 0) + 1]);
                } catch (Exception $e) {
                    $this->logger->error('更新评论点赞数失败', ['comment_id' => $commentId, 'error' => $e->getMessage()]);
                }

                $this->logAction('评论点赞成功', ['comment_id' => $commentId, 'user_id' => $userId]);
                return true;
            },
            '点赞评论失败',
            ['comment_id' => $commentId, 'user_id' => $userId]
        )['data'] ?? false;
    }

    /**
     * 取消点赞评论.
     *
     * @param int $commentId 评论ID
     * @param int $userId 用户ID
     * @return bool 是否取消成功
     */
    public function unlikeComment(int $commentId, int $userId): bool
    {
        return $this->executeWithErrorHandling(
            function () use ($commentId, $userId) {
                // 查找点赞记录
                $likeRecord = $this->commentLikeRepository->findByCommentAndUser($commentId, $userId);
                if (! $likeRecord) {
                    return false; // 没有点赞记录，直接返回false
                }

                // 删除点赞记录
                $result = $this->commentLikeRepository->deleteById($likeRecord['id']);

                if ($result) {
                    // 获取评论信息以更新点赞数
                    $comment = $this->commentRepository->findById($commentId);
                    if ($comment) {
                        // 更新评论点赞数（确保不会小于0）
                        try {
                            $newLikeCount = max(0, ($comment['like_count'] ?? 0) - 1);
                            $this->commentRepository->update($commentId, ['like_count' => $newLikeCount]);
                        } catch (Exception $e) {
                            $this->logger->error('更新评论点赞数失败', ['comment_id' => $commentId, 'error' => $e->getMessage()]);
                        }
                    }

                    $this->logAction('取消评论点赞成功', ['comment_id' => $commentId, 'user_id' => $userId]);
                }

                return $result;
            },
            '取消点赞评论失败',
            ['comment_id' => $commentId, 'user_id' => $userId]
        )['data'] ?? false;
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
        return $this->executeWithErrorHandling(
            function () use ($data) {
                $this->logAction('创建评论', ['data' => $data]);

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
                    'status' => $data['status'] ?? 0, // 默认为0（待审核）
                    'created_at' => time(),
                    'updated_at' => time(),
                ];

                // 使用Repository创建评论
                $success = $this->commentRepository->create($commentData);
                if (! $success) {
                    throw new Exception('创建评论失败');
                }

                // 获取新创建的评论ID
                $commentId = Db::getPdo()->lastInsertId();
                
                // 触发审核通知
                $this->notifyNewCommentForReview((int) $commentId);

                $this->logAction('创建评论成功', ['comment_id' => $commentId]);
                return (int) $commentId;
            },
            '创建评论失败',
            ['data' => $data]
        )['data'] ?? throw new Exception('创建评论失败');
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
        return $this->executeWithErrorHandling(
            function () use ($id, $data) {
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
                
                // 添加更新时间
                $commentData['updated_at'] = time();

                // 使用Repository更新评论数据
                $result = $this->commentRepository->update($id, $commentData);
                
                $this->logAction('更新评论', ['comment_id' => $id, 'success' => $result]);
                return $result;
            },
            '更新评论失败',
            ['comment_id' => $id, 'data' => $data]
        )['data'] ?? false;
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
        // 由于CommentRepository中没有直接的方法，我们扩展一下查询参数并使用findWithPagination
        $pendingParams = $params;
        $pendingParams['status'] = 0; // 待审核状态
        
        // 使用Repository获取待审核评论列表
        return $this->commentRepository->findWithPagination($pendingParams);
    }

    /**
     * 删除评论.
     *
     * @param int $id 评论ID
     * @return bool 删除结果
     */
    public function deleteComment(int $id): bool
    {
        return $this->executeWithErrorHandling(
            function () use ($id) {
                $this->logAction('删除评论', ['comment_id' => $id]);

                // 检查评论是否存在
                $comment = $this->commentRepository->findById($id);
                if (! $comment) {
                    $this->logAction('评论不存在', ['comment_id' => $id], 'warning');
                    return false;
                }

                // 使用Repository删除评论
                $result = $this->commentRepository->delete($id);

                if ($result) {
                    $this->logAction('删除评论成功', ['comment_id' => $id]);
                } else {
                    $this->logAction('删除评论失败', ['comment_id' => $id], 'warning');
                }

                return $result;
            },
            '删除评论失败',
            ['comment_id' => $id]
        )['data'] ?? false;
    }

    /**
     * 获取评论详情.
     *
     * @param int $id 评论ID
     * @return null|array 评论数据
     */
    public function getCommentById(int $id): ?array
    {
        return $this->executeWithErrorHandling(
            function () use ($id) {
                $this->logAction('获取评论详情', ['comment_id' => $id]);

                // 使用Repository获取评论详情
                $result = $this->commentRepository->findById($id);

                if ($result) {
                    $this->logAction('获取评论详情成功', ['comment_id' => $id]);
                } else {
                    $this->logAction('评论不存在', ['comment_id' => $id]);
                }

                return $result;
            },
            '获取评论详情失败',
            ['comment_id' => $id]
        )['data'] ?? null;
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
     * @param null|int $userId 当前用户ID（可选，用于判断是否点赞）
     * @return array{data:array, total:int, page:int, page_size:int} 回复列表
     */
    public function getReplies(int $parentId, array $params = [], ?int $userId = null): array
    {
        return $this->executeWithErrorHandling(
            function () use ($parentId, $params, $userId) {
                // 构建查询参数
                $replyParams = $params;
                $replyParams['parent_id'] = $parentId;
                
                // 非管理员查询时只返回已审核评论
                if (empty($params['include_pending']) || ! $params['include_pending']) {
                    $replyParams['status'] = 1;
                }
                
                $replyParams['order_by'] = 'created_at';
                $replyParams['order_direction'] = 'asc';
                
                // 使用Repository获取回复列表
                $result = $this->commentRepository->findWithPagination($replyParams);
                
                // 添加点赞信息
                $this->enrichCommentsWithLikeData($result['data'], $userId);

                return $result;
            },
            '获取评论回复失败',
            ['parent_id' => $parentId, 'params' => $params]
        )['data'] ?? [
            'data' => [],
            'total' => 0,
            'page' => $params['page'] ?? 1,
            'page_size' => $params['page_size'] ?? 20,
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
        return $this->executeWithErrorHandling(
            function () use ($ids, $status) {
                $this->logAction('批量审核评论', ['comment_ids' => $ids, 'status' => $status]);

                $success = 0;
                $failed = 0;
                $results = [];

                foreach ($ids as $id) {
                    try {
                        $result = $this->updateCommentStatus($id, $status);
                        if ($result) {
                            ++$success;
                            $results[$id] = true;
                            $this->logAction('评论审核成功', ['comment_id' => $id, 'status' => $status]);
                        } else {
                            ++$failed;
                            $results[$id] = false;
                            $this->logAction('评论审核失败', ['comment_id' => $id], 'warning');
                        }
                    } catch (Exception $e) {
                        ++$failed;
                        $results[$id] = false;
                        $this->logAction('评论审核异常', [
                            'comment_id' => $id,
                            'error' => $e->getMessage(),
                        ], 'error');
                    }
                }

                $result = [
                    'total' => count($ids),
                    'success' => $success,
                    'failed' => $failed,
                    'results' => $results,
                ];

                $this->logAction('批量审核评论完成', [
                    'total' => count($ids),
                    'success' => $success,
                    'failed' => $failed,
                ]);

                return $result;
            },
            '批量审核评论失败',
            ['comment_ids' => $ids, 'status' => $status]
        )['data'] ?? [
            'total' => count($ids),
            'success' => 0,
            'failed' => count($ids),
            'results' => array_fill_keys($ids, false),
        ];
    }

    /**
     * 为评论列表添加点赞数据.
     *
     * @param array<string, mixed> $comments 评论列表
     * @param null|int $userId 当前用户ID
     */
    protected function enrichCommentsWithLikeData(array &$comments, ?int $userId = null): void
    {
        if (empty($comments)) {
            return;
        }

        // 获取评论ID列表
        $commentIds = array_column($comments, 'id');

        // 查询点赞数量
        $likeCountMap = $this->commentLikeRepository->countByComments($commentIds);

        // 查询当前用户的点赞记录
        $userLikes = $userId ? $this->commentLikeRepository->checkUserLikes($commentIds, $userId) : [];
        // 将返回的数组转换为以comment_id为键的关联数组，方便查找
        $userLikesMap = array_flip($userLikes);

        // 为评论添加点赞信息
        foreach ($comments as &$comment) {
            $commentId = $comment['id'];
            $comment['likes'] = $likeCountMap[$commentId] ?? 0;
            $comment['user_liked'] = isset($userLikesMap[$commentId]) ? true : false;
        }
    }

    /**
     * 验证评论内容长度.
     *
     * @param string $content 评论内容
     * @throws Exception 当内容超长时抛出异常
     */
    private function validateContentLength(string $content): void
    {
        // 计算中文字符数量（一个中文字符算1个，英文、数字、符号算半个）
        $chineseCount = preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $content) ?: 0;
        $nonChineseCount = mb_strlen((string) preg_replace('/[\x{4e00}-\x{9fa5}]/u', '', $content));
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
        $result = $this->commentRepository->update($id, ['status' => $status]);

        if ($result && $status == 1) {
            // 如果是通过审核，更新相关内容的评论计数
            if (! empty($comment['post_id']) && ! empty($comment['post_type'])) {
                $this->updateCommentCount($comment['post_id'], $comment['post_type']);
            }
        }

        return $result;
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
            } elseif ($postType === 'works') {
                // 如果有WorkRepository，使用它更新评论计数
                // $this->workRepository->updateCommentCount($postId, $commentCount);
                $this->logger->info('更新作品评论计数', ['post_id' => $postId, 'count' => $commentCount]);
            }
        } catch (Exception $e) {
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
        $this->executeWithErrorHandling(
            function () use ($commentId) {
                $this->logAction('通知新评论需要审核', ['comment_id' => $commentId]);

                // 获取评论数据
                $commentData = $this->getCommentById($commentId);

                // 使用依赖注入的事件分发器触发事件
                $this->dispatcher->dispatch(new NewCommentEvent($commentId, $commentData ?? []));

                $this->logAction('新评论审核通知触发成功', ['comment_id' => $commentId]);
                return true;
            },
            '触发新评论审核通知失败',
            ['comment_id' => $commentId]
        );
    }
}
