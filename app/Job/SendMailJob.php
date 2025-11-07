<?php

declare(strict_types=1);

namespace App\Job;

use App\Service\MailService;
use Hyperf\Context\ApplicationContext;

/**
 * 邮件发送任务
 * 用于异步发送邮件
 */
class SendMailJob extends AbstractJob
{
    /**
     * 执行任务
     */
    public function handle()
    {
        $params = $this->getParams();
        $to = $params['to'] ?? '';
        $subject = $params['subject'] ?? '';
        $content = $params['content'] ?? '';
        $type = $params['type'] ?? 'html';

        if (empty($to) || empty($subject)) {
            $this->logger->error('Invalid mail parameters', ['params' => $params]);
            return;
        }

        try {
            $this->logger->info('Starting mail send job', ['to' => $to, 'subject' => $subject]);
            
            // 获取MailService实例
            $mailService = ApplicationContext::getContainer()->get(MailService::class);
            
            // 调用邮件服务发送邮件
            $result = $mailService->send($to, $subject, $content, $type);
            
            if ($result) {
                $this->logger->info('Mail sent successfully', ['to' => $to, 'subject' => $subject]);
            } else {
                $this->logger->error('Mail sending failed', ['to' => $to, 'subject' => $subject]);
                // 抛出异常触发重试机制
                throw new \RuntimeException('Failed to send mail');
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception occurred while sending mail', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // 直接抛出异常，由父类的execute方法处理重试逻辑
            throw $e;
        }
    }
}