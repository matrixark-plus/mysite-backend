<?php

declare(strict_types=1);

namespace App\Listener;

use App\Event\NewCommentEvent;
use Hyperf\Context\ApplicationContext;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * 新评论监听器
 * 监听新评论事件并发送通知
 */
#[Listener]
class NewCommentListener implements ListenerInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 构造函数
     */
    public function __construct()
    {
        // 使用Hyperf内置的LoggerFactory获取logger实例
        $this->logger = ApplicationContext::getContainer()->get(LoggerFactory::class)->get('comment');
    }
    
    /**
     * 构建邮件正文
     * @param int $commentId 评论ID
     * @param array $commentData 评论数据
     * @return string 邮件正文
     */
    protected function buildEmailBody(int $commentId, array $commentData): string
    {
        $appName = env('APP_NAME', '博客系统');
        $body = "尊敬的管理员：\n\n";
        $body .= "您的 {$appName} 收到了一条新评论，需要您的审核。\n\n";
        $body .= "评论详情：\n";
        $body .= "- 评论ID: {$commentId}\n";
        $body .= "- 用户ID: " . ($commentData['user_id'] ?? '未知') . "\n";
        $body .= "- 文章ID: " . ($commentData['post_id'] ?? '未知') . "\n";
        $body .= "- 文章类型: " . ($commentData['post_type'] ?? '未知') . "\n";
        $body .= "- 评论内容: " . ($commentData['content'] ?? '无') . "\n\n";
        $body .= "请尽快登录管理后台进行审核。\n\n";
        $body .= "此邮件由系统自动发送，请勿回复。";
        
        return $body;
    }
    
    /**
     * 监听的事件列表
     */
    public function listen(): array
    {
        return [
            NewCommentEvent::class,
        ];
    }

    /**
     * 处理新评论事件
     * 
     * @param object $event
     */
    public function process(object $event): void
    {
        // 添加类型检查以确保只处理NewCommentEvent类型的事件
        if (!($event instanceof NewCommentEvent)) {
            return;
        }
        
        $commentId = $event->getCommentId();
        $commentData = $event->getCommentData();
        
        // 记录日志
        $this->logger->info('收到新评论需要审核', [
            'comment_id' => $commentId,
            'user_id' => $commentData['user_id'] ?? 0,
            'post_id' => $commentData['post_id'] ?? 0,
            'post_type' => $commentData['post_type'] ?? '',
        ]);
        
        // 集成邮件服务发送通知给管理员
        try {
            // 尝试获取邮件服务
            $container = ApplicationContext::getContainer();
            
            // 构建邮件内容
            $subject = '新评论需要审核 - ID: ' . $commentId;
            $body = $this->buildEmailBody($commentId, $commentData);
            
            // 由于无法确认邮件服务是否已配置，使用条件检查
            if (class_exists('Hyperf\Mail\MailerInterface') && $container->has('Hyperf\Mail\MailerInterface')) {
                $mailer = $container->get('Hyperf\Mail\MailerInterface');
                $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
                
                // 发送邮件
                $mailer->send(function ($message) use ($adminEmail, $subject, $body) {
                    $message->to($adminEmail)
                            ->subject($subject)
                            ->text($body);
                });
                
                $this->logger->info('新评论邮件通知已发送', ['comment_id' => $commentId]);
            } else {
                // 如果邮件服务不可用，记录日志并继续
                $this->logger->warning('邮件服务未配置，无法发送新评论通知邮件', ['comment_id' => $commentId]);
            }
        } catch (\Exception $e) {
            // 捕获并记录邮件发送异常，但不影响主流程
            $this->logger->error('发送新评论通知邮件失败', [
                'comment_id' => $commentId,
                'error' => $e->getMessage()
            ]);
        }
    }
}