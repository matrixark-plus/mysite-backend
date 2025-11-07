<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Context\ApplicationContext;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Logger\Logger;
use Psr\Log\LoggerInterface;

/**
 * 协程邮件服务
 * 基于协程方式异步实现邮件发送，支持多邮箱服务商
 */
class MailService
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 邮件配置缓存
     * @var array
     */
    protected $mailConfig = [];

    public function __construct()
    {
        $container = ApplicationContext::getContainer();
        $this->config = $container->get(ConfigInterface::class);
        $this->logger = $container->get(LoggerInterface::class);
        
        // 初始化邮件配置
        $this->initMailConfig();
    }

    /**
     * 初始化邮件配置
     */
    protected function initMailConfig(): void
    {
        // 从配置文件加载邮件配置
        $this->mailConfig = $this->config->get('mail', []);
    }

    /**
     * 根据邮箱类型获取SMTP配置
     *
     * @param string $emailType 邮箱类型 (163, aliyun, gmail)
     * @return array SMTP配置
     */
    public function getSmtpConfig(string $emailType = 'default'): array
    {
        $smtpConfig = $this->mailConfig['smtp'] ?? [];
        $fromConfig = $this->mailConfig['from'] ?? [];
        
        // 获取特定类型的配置，如果不存在则使用默认配置
        $config = $smtpConfig[$emailType] ?? $smtpConfig['default'] ?? [];
        
        // 添加发件人配置
        $config['from'] = $fromConfig;
        
        return $config;
    }

    /**
     * 异步发送邮件
     *
     * @param array|string $to 收件人邮箱
     * @param string $subject 邮件主题
     * @param string $content 邮件内容
     * @param bool $isHtml 是否为HTML内容
     * @param array $options 额外选项
     * @return Coroutine|null 协程对象
     */
    public function sendAsync($to, string $subject, string $content, bool $isHtml = true, array $options = []): ?Coroutine
    {
        return Coroutine::create(function () use ($to, $subject, $content, $isHtml, $options) {
            try {
                $this->sendSync($to, $subject, $content, $isHtml, $options);
            } catch (\Throwable $e) {
                $this->logger->error('异步邮件发送失败: ' . $e->getMessage());
                $this->logger->error($e->getTraceAsString());
            }
        });
    }

    /**
     * 同步发送邮件
     *
     * @param array|string $to 收件人邮箱
     * @param string $subject 邮件主题
     * @param string $content 邮件内容
     * @param bool $isHtml 是否为HTML内容
     * @param array $options 额外选项
     * @return bool 是否发送成功
     */
    public function sendSync($to, string $subject, string $content, bool $isHtml = true, array $options = []): bool
    {
        try {
            // 获取邮件配置
            $emailType = $options['email_type'] ?? 'default';
            $config = $this->getSmtpConfig($emailType);

            // 如果提供了自定义配置，使用自定义配置
            if (isset($options['smtp_config'])) {
                $config = array_merge($config, $options['smtp_config']);
            }

            // 构建邮件头
            $toEmails = is_array($to) ? $to : [$to];
            $headers = [];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=utf-8';
            $headers[] = 'From: ' . $config['from']['name'] . ' <' . $config['from']['address'] . '>';
            $headers[] = 'Reply-To: ' . $config['from']['address'];
            $headers[] = 'X-Mailer: PHP/' . phpversion();

            // 使用SMTP发送邮件
            return $this->sendBySmtp($toEmails, $subject, $content, $headers, $config);
        } catch (\Throwable $e) {
            $this->logger->error('同步邮件发送失败: ' . $e->getMessage());
            $this->logger->error($e->getTraceAsString());
            return false;
        }
    }

    /**
     * 通过SMTP发送邮件
     *
     * @param array $toEmails 收件人邮箱列表
     * @param string $subject 邮件主题
     * @param string $message 邮件内容
     * @param array $headers 邮件头
     * @param array $config SMTP配置
     * @return bool 是否发送成功
     */
    protected function sendBySmtp(array $toEmails, string $subject, string $message, array $headers, array $config): bool
    {
        // 检查是否支持fsockopen
        if (!function_exists('fsockopen')) {
            throw new \RuntimeException('PHP fsockopen function is not available');
        }

        // 连接到SMTP服务器
        $socket = null;
        $address = $config['host'];
        $port = $config['port'];
        $timeout = 30;

        // 根据加密类型连接
        if ($config['encryption'] === 'ssl') {
            $socket = fsockopen('ssl://' . $address, $port, $errno, $errstr, $timeout);
        } elseif ($config['encryption'] === 'tls') {
            $socket = fsockopen($address, $port, $errno, $errstr, $timeout);
            if ($socket) {
                $this->sendCommand($socket, 'STARTTLS');
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }
        } else {
            $socket = fsockopen($address, $port, $errno, $errstr, $timeout);
        }

        if (!$socket) {
            throw new \RuntimeException('无法连接到SMTP服务器: ' . $errstr);
        }

        try {
            // 发送SMTP命令
            $this->readResponse($socket); // 读取服务器欢迎信息
            $this->sendCommand($socket, 'EHLO ' . gethostname());
            $this->sendCommand($socket, 'AUTH LOGIN');
            $this->sendCommand($socket, base64_encode($config['username']));
            $this->sendCommand($socket, base64_encode($config['password']));
            $this->sendCommand($socket, 'MAIL FROM: <' . $config['from']['address'] . '>');

            foreach ($toEmails as $to) {
                $this->sendCommand($socket, 'RCPT TO: <' . $to . '>');
            }

            $this->sendCommand($socket, 'DATA');

            // 构建完整邮件内容
            $email = '';
            $email .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=' . "\r\n";
            $email .= implode("\r\n", $headers) . "\r\n\r\n";
            $email .= $message . "\r\n";
            $email .= ".";

            $this->sendCommand($socket, $email);
            $this->sendCommand($socket, 'QUIT');

            return true;
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            if ($socket) {
                fclose($socket);
            }
        }
    }

    /**
     * 发送SMTP命令
     *
     * @param resource $socket 套接字资源
     * @param string $command 命令内容
     */
    protected function sendCommand($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
        $this->readResponse($socket);
    }

    /**
     * 读取SMTP响应
     *
     * @param resource $socket 套接字资源
     * @return string 响应内容
     */
    protected function readResponse($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }

        // 检查响应码
        $code = substr($response, 0, 3);
        if ($code >= 400) {
            throw new \RuntimeException('SMTP错误: ' . $response);
        }

        return $response;
    }

    /**
     * 发送注册确认邮件
     *
     * @param string $email 用户邮箱
     * @param string $username 用户名
     * @param string $token 确认token
     * @param array $options 额外选项
     * @return Coroutine|null 协程对象
     */
    public function sendRegisterConfirmation(string $email, string $username, string $token, array $options = []): ?Coroutine
    {
        $confirmationUrl = env('APP_URL', '') . '/api/user/confirm/' . $token;
        $content = <<<HTML
        <html>
        <body>
            <h2>欢迎注册我们的网站，{$username}！</h2>
            <p>请点击以下链接完成邮箱验证：</p>
            <p><a href="{$confirmationUrl}">点击验证邮箱</a></p>
            <p>如果无法点击，请复制以下链接到浏览器：</p>
            <p>{$confirmationUrl}</p>
            <p>谢谢！</p>
        </body>
        </html>
        HTML;

        return $this->sendAsync($email, '注册确认', $content, true, $options);
    }

    /**
     * 发送密码重置邮件
     *
     * @param string $email 用户邮箱
     * @param string $username 用户名
     * @param string $token 重置token
     * @param array $options 额外选项
     * @return Coroutine|null 协程对象
     */
    public function sendPasswordReset(string $email, string $username, string $token, array $options = []): ?Coroutine
    {
        $resetUrl = env('APP_URL', '') . '/api/user/reset-password/' . $token;
        $content = <<<HTML
        <html>
        <body>
            <h2>密码重置请求，{$username}！</h2>
            <p>您请求了密码重置，请点击以下链接：</p>
            <p><a href="{$resetUrl}">重置密码</a></p>
            <p>如果无法点击，请复制以下链接到浏览器：</p>
            <p>{$resetUrl}</p>
            <p>如果您没有请求此操作，请忽略此邮件。</p>
            <p>谢谢！</p>
        </body>
        </html>
        HTML;

        return $this->sendAsync($email, '密码重置请求', $content, true, $options);
    }

    /**
     * 发送联系表单邮件
     *
     * @param array $formData 表单数据
     * @param array $options 额外选项
     * @return Coroutine|null 协程对象
     */
    public function sendContactForm(array $formData, array $options = []): ?Coroutine
    {
        $to = env('CONTACT_EMAIL', 'admin@example.com');
        $content = <<<HTML
        <html>
        <body>
            <h2>新的联系表单提交</h2>
            <p><strong>姓名：</strong>{$formData['name']}</p>
            <p><strong>邮箱：</strong>{$formData['email']}</p>
            <p><strong>主题：</strong>{$formData['subject']}</p>
            <p><strong>消息：</strong></p>
            <p>{$formData['message']}</p>
        </body>
        </html>
        HTML;

        return $this->sendAsync($to, '新的联系表单提交: ' . $formData['subject'], $content, true, $options);
    }
    
    /**
     * 发送验证码邮件
     *
     * @param string $email 收件人邮箱
     * @param string $code 验证码
     * @param int $expireMinutes 过期时间（分钟）
     * @param array $options 额外选项
     * @return Coroutine|null 协程对象
     */
    public function sendVerifyCode(string $email, string $code, int $expireMinutes = 10, array $options = []): ?Coroutine
    {
        $subject = '【个人网站】验证码';
        $content = <<<HTML
        <h2>验证码</h2>
        <p>您的验证码是: <strong>$code</strong></p>
        <p>验证码将在 $expireMinutes 分钟后过期，请及时使用。</p>
        <p>如果这不是您的操作，请忽略此邮件。</p>
        HTML;
        
        return $this->sendAsync($email, $subject, $content, true, $options);
    }
    
    /**
     * 发送订阅确认邮件
     *
     * @param string $email 收件人邮箱
     * @param string $confirmUrl 确认链接
     * @param array $options 额外选项
     * @return Coroutine|null 协程对象
     */
    public function sendSubscribeConfirmation(string $email, string $confirmUrl, array $options = []): ?Coroutine
    {
        $subject = '【个人网站】订阅确认';
        $content = <<<HTML
        <h2>订阅确认</h2>
        <p>感谢您订阅我们的博客更新！</p>
        <p>请点击下方链接确认您的订阅：</p>
        <p><a href="$confirmUrl" target="_blank">确认订阅</a></p>
        <p>如果这不是您的操作，请忽略此邮件。</p>
        HTML;
        
        return $this->sendAsync($email, $subject, $content, true, $options);
    }
    
    /**
     * 发送评论通知邮件
     *
     * @param string $email 管理员邮箱
     * @param array $commentData 评论数据
     * @param array $options 额外选项
     * @return Coroutine|null 协程对象
     */
    public function sendCommentNotification(string $email, array $commentData, array $options = []): ?Coroutine
    {
        $subject = '【个人网站】新评论需要审核';
        $content = <<<HTML
        <h2>新评论通知</h2>
        <p>用户 <strong>{$commentData['username']}</strong> 发表了一条新评论，需要您的审核。</p>
        <p><strong>评论内容：</strong><br>{$commentData['content']}</p>
        <p><strong>相关内容：</strong>{$commentData['post_title']}</p>
        <p><strong>评论时间：</strong>{$commentData['created_at']}</p>
        HTML;
        
        return $this->sendAsync($email, $subject, $content, true, $options);
    }
}