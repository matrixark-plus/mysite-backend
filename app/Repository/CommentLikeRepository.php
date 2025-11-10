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

namespace App\Repository;

use Exception;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;
use Throwable;

class CommentLikeRepository extends BaseRepository
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 表名.
     * @var string
     */
    protected $table = 'comment_likes';

    /**
     * 根据评论ID和用户ID查找点赞记录.
     *
     * @param int $commentId 评论ID
     * @param int $userId 用户ID
     * @return null|array 点赞记录数组，不存在则返回null
     */
    public function findByCommentAndUser(int $commentId, int $userId): ?array
    {
        try {
            return Db::table($this->table)
                ->where('comment_id', $commentId)
                ->where('user_id', $userId)
                ->first() ?: null;
        } catch (Exception $e) {
            $this->logger->error('查找评论点赞记录失败', [
                'comment_id' => $commentId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 根据评论ID统计点赞数.
     *
     * @param int $commentId 评论ID
     * @return int 点赞总数
     */
    public function countByComment(int $commentId): int
    {
        try {
            return Db::table($this->table)
                ->where('comment_id', $commentId)
                ->count();
        } catch (Exception $e) {
            $this->logger->error('统计评论点赞数失败', [
                'comment_id' => $commentId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * 创建点赞记录.
     *
     * @param array $data 点赞数据
     * @return bool 是否创建成功
     */
    public function create(array $data): bool
    {
        try {
            return Db::table($this->table)->insert($data) > 0;
        } catch (Exception $e) {
            $this->logger->error('创建评论点赞记录失败', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 根据ID删除点赞记录.
     *
     * @param int $id 点赞记录ID
     * @return bool 是否删除成功
     */
    public function deleteById(int $id): bool
    {
        try {
            return Db::table($this->table)->where('id', $id)->delete() > 0;
        } catch (Exception $e) {
            $this->logger->error('删除评论点赞记录失败', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 执行事务操作.
     *
     * @param callable $callback 事务回调函数
     * @return mixed 回调函数的返回值
     * @throws Throwable
     */
    public function transaction(callable $callback)
    {
        return Db::transaction($callback);
    }

    /**
     * 统计多个评论的点赞数.
     *
     * @param array $commentIds 评论ID数组
     * @return array 评论ID对应点赞数的数组
     */
    public function countByComments(array $commentIds): array
    {
        try {
            $results = Db::table($this->table)
                ->select('comment_id', Db::raw('count(*) as like_count'))
                ->whereIn('comment_id', $commentIds)
                ->groupBy('comment_id')
                ->get();

            $counts = [];
            foreach ($results as $result) {
                $counts[$result->comment_id] = $result->like_count;
            }

            return $counts;
        } catch (Exception $e) {
            $this->logger->error('批量统计评论点赞数失败', [
                'comment_ids' => $commentIds,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * 检查用户是否点赞了多个评论.
     *
     * @param array $commentIds 评论ID数组
     * @param int $userId 用户ID
     * @return array 已点赞的评论ID数组
     */
    public function checkUserLikes(array $commentIds, int $userId): array
    {
        try {
            return Db::table($this->table)
                ->whereIn('comment_id', $commentIds)
                ->where('user_id', $userId)
                ->pluck('comment_id')
                ->toArray();
        } catch (Exception $e) {
            $this->logger->error('检查用户评论点赞状态失败', [
                'comment_ids' => $commentIds,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * 获取模型类名.
     * @return string 模型类名
     */
    protected function getModel(): string
    {
        return 'App\Model\CommentLike';
    }
}
