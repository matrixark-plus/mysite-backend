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

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Middleware\JwtAuthMiddleware;
use App\Service\MailService;
use App\Service\VerifyCodeService;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

/**
 * @Controller(prefix="/api/email")
 */
class EmailController extends AbstractController
{
    /**
     * @Inject
     * @var MailService
     */
    protected $mailService;

    /**
     * @Inject
     * @var VerifyCodeService
     */
    protected $verifyCodeService;

    /**
     * 发送邮件（管理员）.
     *
     * @RequestMapping(path="/send", methods={"POST"})
     * @Middleware({JwtAuthMiddleware::class, "admin"})
     */
    public function send(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $to = $request->input('to');
            $subject = $request->input('subject');
            $template = $request->input('template');
            $data = $request->input('data', []);

            // 验证参数
            if (! $to || ! $subject) {
                return $this->fail(400, '缺少必要参数');
            }

            // 构建邮件内容
            $body = $this->buildEmailBody($template, $data);

            // 发送邮件
            $result = $this->mailService->sendSync($to, $subject, $body);

            if ($result) {
                return $this->success(null, '邮件发送成功');
            }
            return $this->fail(500, '邮件发送失败');
        } catch (Exception $e) {
            logger()->error('邮件发送异常: ' . $e->getMessage());
            return $this->fail(500, '服务器内部错误');
        }
    }

    /**
     * 发送验证码
     *
     * @RequestMapping(path="/verify-code", methods={"POST"})
     */
    public function verifyCode(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $email = $request->input('email');

            // 验证邮箱格式
            if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->fail(400, '邮箱格式不正确');
            }

            // 发送验证码
            $result = $this->verifyCodeService->sendEmailCode($email);

            if ($result['success']) {
                return $this->success(null, $result['message']);
            }
            return $this->fail(400, $result['message']);
        } catch (Exception $e) {
            logger()->error('发送验证码异常: ' . $e->getMessage());
            return $this->fail(500, '服务器内部错误');
        }
    }

    /**
     * 构建邮件内容.
     *
     * @param string $template 模板名称
     * @param array $data 模板数据
     * @return string
     */
    protected function buildEmailBody($template, $data)
    {
        // 如果没有指定模板，使用默认内容
        if (! $template) {
            return isset($data['content']) ? $data['content'] : '';
        }

        // 根据模板名称构建不同的邮件内容
        switch ($template) {
            case 'welcome':
                return $this->buildWelcomeEmail($data);
            case 'notify':
                return $this->buildNotifyEmail($data);
            default:
                return isset($data['content']) ? $data['content'] : '';
        }
    }

    /**
     * 构建欢迎邮件.
     *
     * @param array $data
     * @return string
     */
    protected function buildWelcomeEmail($data)
    {
        $username = $data['username'] ?? '用户';
        return <<<HTML
        <h2>欢迎加入个人网站！</h2>
        <p>尊敬的 {$username}：</p>
        <p>欢迎您注册成为我们的会员！</p>
        <p>您的账户已成功创建，您可以开始浏览和使用我们的服务了。</p>
        <p>如有任何问题，请随时联系我们。</p>
        <p>祝您使用愉快！</p>
        HTML;
    }

    /**
     * 构建通知邮件.
     *
     * @param array $data
     * @return string
     */
    protected function buildNotifyEmail($data)
    {
        $title = $data['title'] ?? '系统通知';
        $content = $data['content'] ?? '';

        return <<<HTML
        <h2>{$title}</h2>
        <div>{$content}</div>
        <p>这是一条系统自动发送的通知，请不要回复此邮件。</p>
        HTML;
    }
}
