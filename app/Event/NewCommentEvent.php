<?php

declare(strict_types=1);

namespace App\Event;

/**
 * 新评论事件
 * 当有新评论提交时触发此事件
 */
class NewCommentEvent
{
    /**
     * 评论ID
     * 
     * @var int
     */
    protected $commentId;

    /**
     * 评论数据
     * 
     * @var array
     */
    protected $commentData;

    /**
     * 构造函数
     * 
     * @param int $commentId 评论ID
     * @param array $commentData 评论数据
     */
    public function __construct(int $commentId, array $commentData)
    {
        $this->commentId = $commentId;
        $this->commentData = $commentData;
    }

    /**
     * 获取评论ID
     * 
     * @return int
     */
    public function getCommentId(): int
    {
        return $this->commentId;
    }

    /**
     * 获取评论数据
     * 
     * @return array
     */
    public function getCommentData(): array
    {
        return $this->commentData;
    }
}