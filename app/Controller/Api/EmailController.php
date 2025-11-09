<?php

declare(strict_types=1);
/**
 * 邮件控制器
 * 处理邮件发送和验证码相关功能
 */

namespace App\Controller\Api;

use App\Constants\StatusCode;
use App\Controller\AbstractController;
use App\Controller\Api\Validator\EmailValidator;
use App\Middleware\JwtAuthMiddleware;
use App\Service\MailService;
use App\Service\VerifyCodeService;
use App\Traits\LogTrait;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\RequestMethod;
use Hyperf\Validation\ValidationException;

/**
 * @Controller(prefix="/api/email")
 */
class EmailController extends AbstractController
{
    use LogTrait;

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
     * @Inject
     * @var EmailValidator
     */
    protected $validator;

    /**
     * 发送邮件（管理员）
     * @return ResponseInterface
     * @RequestMapping(path="/send", methods={"POST"})
     * @Middleware({JwtAuthMiddleware::class, "admin"})
     */
    public function send()
    {
        try {
            // 使用验证器验证参数
            try {
                $validatedData = $this->validator->validateSendEmail($this->request->all());
                $to = $validatedData['to'];
                $subject = $validatedData['subject'];
                $template = $validatedData['template'] ?? '';
                $data = $validatedData['data'] ?? [];
            } catch (ValidationException $e) {
                return $this->fail(StatusCode::BAD_REQUEST, $e->validator->errors()->first());
            }

            // 构建邮件内容
            $body = $this->buildEmailBody($template, $data);

            // 发送邮件
            $result = $this->mailService->sendSync($to, $subject, $body);

            if ($result) {
                return $this->success(null, '邮件发送成功');
            }
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '邮件发送失败');
        } catch (Exception $e) {
            $this->logError('邮件发送异常', ['error' => $e->getMessage()], $e);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '服务器内部错误');
        }
    }

    /**
     * 发送验证码
     * @return ResponseInterface
     * @RequestMapping(path="/verify-code", methods={"POST"})
     */
    public function verifyCode()
    {
        try {
            // 验证参数
            try {
                $validatedData = $this->validator->validateVerifyCode($this->request->all());
                $email = $validatedData['email'];
            } catch (ValidationException $e) {
                return $this->fail(StatusCode::BAD_REQUEST, $e->validator->errors()->first());
            }

            // 发送验证码
            $result = $this->verifyCodeService->sendEmailCode($email);

            if ($result['success']) {
                return $this->success(null, $result['message']);
            }
            return $this->fail(StatusCode::BAD_REQUEST, $result['message']);
        } catch (Exception $e) {
            $this->logError('发送验证码异常', ['error' => $e->getMessage()], $e);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '服务器内部错误');
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
