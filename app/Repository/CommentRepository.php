<?php

declare(strict_types=1);
/**
 * 评论数据访问层
 */
namespace App\Repository;

use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;
use Hyperf\Di\Annotation\Inject;

class CommentRepository extends BaseRepository
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * 表名
     * @var string
     */
    protected $table = 'comments';
    
    /**
     * 带分页的评论查询
     * @param array $params 查询参数
     * @return array 分页结果
     */
    public function findWithPagination(array $params = []): array
    {
        try {
            $query = Db::table($this->table)
                ->select('comments.*', 'users.username', 'users.avatar')
                ->leftJoin('users', 'comments.user_id', '=', 'users.id')
                ->where('comments.status', '=', 1); // 只查询已审核通过的评论

            // 根据内容类型过滤
            if (isset($params['content_type'])) {
                $query->where('comments.content_type', '=', $params['content_type']);
            }

            // 根据内容ID过滤
            if (isset($params['content_id'])) {
                $query->where('comments.post_id', '=', $params['content_id']);
            }

            // 排序
            $query->orderBy('comments.created_at', 'desc');

            // 分页
            $page = $params['page'] ?? 1;
            $pageSize = $params['page_size'] ?? 10;
            $offset = ($page - 1) * $pageSize;

            $total = $query->count();
            $items = $query->offset($offset)->limit($pageSize)->get()->toArray();

            return [
                'data' => $items,
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
            ];
        } catch (\Exception $e) {
            $this->logger->error('评论分页查询失败', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * 创建评论
     * @param array $data 评论数据
     * @return int 评论ID
     */
    public function create(array $data): int
    {
        try {
            return Db::table($this->table)->insertGetId($data);
        } catch (\Exception $e) {
            $this->logger->error('创建评论失败', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * 根据ID获取评论
     * @param int $id 评论ID
     * @return array|null 评论信息
     */
    public function findById(int $id): ?array
    {
        try {
            $comment = Db::table($this->table)
                ->where('id', $id)
                ->first();
            return $comment ? (array) $comment : null;
        } catch (\Exception $e) {
            $this->logger->error('查询评论详情失败', ['id' => $id, 'error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * 更新评论
     * @param int $id 评论ID
     * @param array $data 更新数据
     * @return bool 是否成功
     */
    public function update(int $id, array $data): bool
    {
        try {
            return Db::table($this->table)
                ->where('id', $id)
                ->update($data) > 0;
        } catch (\Exception $e) {
            $this->logger->error('更新评论失败', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 删除评论
     * @param int $id 评论ID
     * @return bool 是否成功
     */
    public function delete(int $id): bool
    {
        try {
            return Db::table($this->table)
                ->where('id', $id)
                ->delete() > 0;
        } catch (\Exception $e) {
            $this->logger->error('删除评论失败', ['id' => $id, 'error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * 获取用户评论总数
     * @param int $userId 用户ID
     * @return int 评论总数
     */
    public function countByUserId(int $userId): int
    {
        try {
            return Db::table($this->table)
                ->where('user_id', $userId)
                ->where('status', '=', 1)
                ->count();
        } catch (\Exception $e) {
            $this->logger->error('统计用户评论数失败', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return 0;
        }
    }
}