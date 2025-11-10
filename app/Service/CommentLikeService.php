<?php

declare(strict_types=1);
/**
 * 评论点赞服务
 * 提供评论点赞相关的业务逻辑处理
 */

namespace App\Service;

use App\Model\CommentLike;
use App\Repository\CommentLikeRepository;
use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;

class CommentLikeService
{    
    /**
     * @var LoggerInterface
     */
    private $logger;
    
    /**
     * @var CommentLikeRepository
     */
    private $commentLikeRepository;
    
    /**
     * CommentLikeService constructor.
     * @param LoggerInterface $logger
     * @param CommentLikeRepository $commentLikeRepository
     */
    public function __construct(LoggerInterface $logger, CommentLikeRepository $commentLikeRepository)
    {
        $this->logger = $logger;
        $this->commentLikeRepository = $commentLikeRepository;
    }

    /**
     * 点赞/取消点赞评论.
     * @param int $commentId 评论ID
     * @param int $userId 用户ID
     * @return array 操作结果
     */
    public function likeComment(int $commentId, int $userId): array
    {
        // 检查是否已点赞
        $existingLike = $this->commentLikeRepository->findByCommentAndUser($commentId, $userId);

        // 使用Repository的事务方法处理
        return $this->commentLikeRepository->transaction(function () use ($commentId, $userId, $existingLike) {
            if ($existingLike) {
                // 已点赞，取消点赞
                $this->commentLikeRepository->deleteById($existingLike['id']);
                $liked = false;
                $message = '取消点赞';
            } else {
                // 未点赞，添加点赞
                $this->commentLikeRepository->create([
                    'comment_id' => $commentId,
                    'user_id' => $userId,
                ]);
                $liked = true;
                $message = '点赞成功';
            }

            // 获取更新后的点赞数
            $likeCount = $this->commentLikeRepository->countByComment($commentId);

            // 记录操作日志
            $this->logger->info('评论点赞操作', [
                'comment_id' => $commentId,
                'user_id' => $userId,
                'action' => $liked ? 'like' : 'unlike',
                'like_count' => $likeCount,
            ]);

            return [
                'liked' => $liked,
                'like_count' => $likeCount,
                'message' => $message,
            ];
        });
    }

    /**
     * 批量获取评论的点赞状态
     * @param array $commentIds 评论ID数组
     * @param null|int $userId 用户ID（可选）
     * @return array 点赞状态映射
     */
    public function getBatchLikeStatus(array $commentIds, ?int $userId = null): array
    {
        if (empty($commentIds)) {
            return [];
        }

        // 获取点赞数
        $likeCounts = CommentLike::select('comment_id', Db::raw('count(*) as count'))
            ->whereIn('comment_id', $commentIds)
            ->groupBy('comment_id')
            ->get()
            ->toArray();

        $likeCountMap = [];
        foreach ($likeCounts as $item) {
            $likeCountMap[$item['comment_id']] = $item['count'];
        }

        // 获取用户点赞状态（如果提供了用户ID）
        $userLikes = [];
        if ($userId) {
            $userLikeRecords = CommentLike::whereIn('comment_id', $commentIds)
                ->where('user_id', $userId)
                ->select('comment_id')
                ->get()
                ->toArray();

            foreach ($userLikeRecords as $record) {
                $userLikes[$record['comment_id']] = true;
            }
        }

        // 构建结果
        $result = [];
        foreach ($commentIds as $commentId) {
            $result[$commentId] = [
                'like_count' => $likeCountMap[$commentId] ?? 0,
                'is_liked' => $userLikes[$commentId] ?? false,
            ];
        }

        return $result;
    }

    /**
     * 获取用户点赞的评论ID列表.
     * @param int $userId 用户ID
     * @param int $limit 限制数量
     * @return array 评论ID数组
     */
    public function getUserLikedComments(int $userId, int $limit = 100): array
    {
        try {
            return CommentLike::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->select('comment_id')
                ->pluck('comment_id')
                ->toArray();
        } catch (\Exception $e) {
            $this->logger->error('获取用户点赞的评论列表失败', [
                'user_id' => $userId,
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * 检查用户是否已点赞（与模型方法对应）
     * 
     * @param int $commentId 评论ID
     * @param int $userId 用户ID
     * @return bool 是否已点赞
     */
    public function isLiked(int $commentId, int $userId): bool
    {
        try {
            return $this->commentLikeRepository->findByCommentAndUser($commentId, $userId) !== null;
        } catch (\Exception $e) {
            $this->logger->error('检查评论点赞状态失败', [
                'comment_id' => $commentId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 获取评论点赞数（与模型方法对应）
     * 
     * @param int $commentId 评论ID
     * @return int 点赞数量
     */
    public function getLikeCount(int $commentId): int
    {
        try {
            return $this->commentLikeRepository->countByComment($commentId);
        } catch (\Exception $e) {
            $this->logger->error('获取评论点赞数失败', [
                'comment_id' => $commentId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}
