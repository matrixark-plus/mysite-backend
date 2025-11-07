<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\ResponseMessage;
use App\Model\Comment;
use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Collection;

/**
 * 评论服务
 * 处理评论的增删改查功能
 */
class CommentService
{
    /**
     * 获取评论列表
     *
     * @param array $params 查询参数
     * @return array 评论列表和总数
     */
    public function getComments(array $params = []): array
    {
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

        return [
            'data' => $comments,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    /**
     * 创建评论
     *
     * @param array $data 评论数据
     * @return int 评论ID
     * @throws \Exception 当评论内容超长时抛出异常
     */
    public function createComment(array $data): int
    {
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
        
        return $comment->id;
    }
    
    /**
     * 验证评论内容长度
     *
     * @param string $content 评论内容
     * @throws \Exception 当内容超长时抛出异常
     */
    private function validateContentLength(string $content): void
    {
        // 计算中文字符数量（一个中文字符算1个，英文、数字、符号算半个）
        $chineseCount = preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $content);
        $nonChineseCount = mb_strlen(preg_replace('/[\x{4e00}-\x{9fa5}]/u', '', $content));
        $totalLength = $chineseCount + $nonChineseCount / 2;
        
        // 限制在1000个中文字符以内
        if ($totalLength > 1000) {
            throw new \Exception(ResponseMessage::COMMENT_CONTENT_LENGTH_EXCEEDED);
        }
    }

    /**
     * 更新评论
     *
     * @param int $id 评论ID
     * @param array $data 评论数据
     * @return bool 更新是否成功
     * @throws \Exception 当评论内容超长时抛出异常
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
     * 审核通过评论
     *
     * @param int $id 评论ID
     * @return bool 操作是否成功
     */
    public function approveComment(int $id): bool
    {
        return $this->updateCommentStatus($id, 1);
    }
    
    /**
     * 拒绝评论
     *
     * @param int $id 评论ID
     * @return bool 操作是否成功
     */
    public function rejectComment(int $id): bool
    {
        return $this->updateCommentStatus($id, 2);
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
        if (!$comment) {
            return false;
        }
        
        // 更新状态
        $result = Db::table('comments')
            ->where('id', '=', $id)
            ->update(['status' => $status]);
            
        if ($result && $status == 1) {
            // 如果是通过审核，更新相关内容的评论计数
            if (!empty($comment['post_id']) && !empty($comment['post_type'])) {
                $this->updateCommentCount($comment['post_id'], $comment['post_type']);
            }
        }
            
        return $result > 0;
    }
    
    /**
     * 更新评论计数
     *
     * @param int $postId 内容ID
     * @param string $postType 内容类型
     */
    private function updateCommentCount(int $postId, string $postType): void
    {
        $table = $postType === 'blog' ? 'blogs' : 'works';
        
        // 计算该内容的已审核评论数
        $commentCount = Db::table('comments')
            ->where('post_id', '=', $postId)
            ->where('post_type', '=', $postType)
            ->where('status', '=', 1)
            ->count();
            
        // 更新内容的评论计数
        Db::table($table)
            ->where('id', '=', $postId)
            ->update(['comment_count' => $commentCount]);
    }
    
    /**
     * 获取待审核评论列表
     *
     * @param array $params 查询参数
     * @return array 评论列表
     */
    public function getPendingComments(array $params = []): array
    {
        $query = Db::table('comments')
            ->select('comments.*', 'users.username', 'users.avatar', 
                    'blogs.title as blog_title', 'works.title as work_title')
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
     * 通知新评论需要审核
     *
     * @param int $commentId 评论ID
     */
    private function notifyNewCommentForReview(int $commentId): void
    {
        // 使用Hyperf的事件系统触发新评论事件
        $container = ApplicationContext::getContainer();
        $dispatcher = $container->get(Hyperf\Event\EventDispatcherInterface::class);
        
        // 获取评论数据
        $commentData = $this->getCommentById($commentId);
        
        // 触发事件
        $dispatcher->dispatch(new \App\Event\NewCommentEvent($commentId, $commentData ?? []));
    }

    /**
     * 删除评论
     *
     * @param int $id 评论ID
     * @return bool 是否成功
     */
    public function deleteComment(int $id): bool
    {
        $comment = Comment::find($id);
        if (!$comment) {
            return false;
        }
        return $comment->delete();
    }

    /**
     * 获取评论详情
     *
     * @param int $id 评论ID
     * @return array|null 评论详情
     */
    public function getCommentById(int $id): ?array
    {
        $comment = Comment::with('user:id,username,avatar')
            ->find($id);
        
        return $comment ? $comment->toArray() : null;
    }

    /**
     * 回复评论
     *
     * @param int $parentId 父评论ID
     * @param array $data 回复数据
     * @return int 回复ID
     */
    public function replyComment(int $parentId, array $data): int
    {
        // 设置父评论ID
        $data['parent_id'] = $parentId;
        return $this->createComment($data);
    }

    /**
     * 获取评论的回复
     *
     * @param int $parentId 父评论ID
     * @param array $params 查询参数
     * @return array 回复列表
     */
    public function getReplies(int $parentId, array $params = []): array
    {
        $query = Db::table('comments')
            ->select('comments.*', 'users.username', 'users.avatar')
            ->leftJoin('users', 'comments.user_id', '=', 'users.id')
            ->where('parent_id', '=', $parentId);
            
        // 非管理员查询时只返回已审核评论
        if (empty($params['include_pending']) || !$params['include_pending']) {
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

        return [
            'data' => $replies,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }
    
    /**
     * 批量审核评论
     *
     * @param array $ids 评论ID数组
     * @param int $status 审核状态
     * @return array 审核结果
     */
    public function batchReviewComments(array $ids, int $status): array
    {
        $success = 0;
        $failed = 0;
        $results = [];
        
        foreach ($ids as $id) {
            try {
                $result = $this->updateCommentStatus($id, $status);
                if ($result) {
                    $success++;
                    $results[$id] = true;
                } else {
                    $failed++;
                    $results[$id] = false;
                }
            } catch (\Exception $e) {
                $failed++;
                $results[$id] = false;
            }
        }
        
        return [
            'total' => count($ids),
            'success' => $success,
            'failed' => $failed,
            'results' => $results
        ];
    }
}