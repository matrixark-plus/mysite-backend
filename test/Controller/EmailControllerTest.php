\u003c?php

declare(strict_types=1);

namespace HyperfTest\Controller;

use App\Controller\Api\EmailController;
use App\Service\MailService;
use App\Service\VerifyCodeService;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Logger\LoggerInterface;
use Hyperf\Testing\TestCase;
use Mockery;

/**
 * EmailController的单元测试
 * 测试邮件控制器的各项功能
 */
class EmailControllerTest extends TestCase
{
    /**
     * @var EmailController
     */
    protected $controller;

    /**
     * @var Mockery\MockInterface|MailService
     */
    protected $mailServiceMock;

    /**
     * @var Mockery\MockInterface|VerifyCodeService
     */
    protected $verifyCodeServiceMock;

    /**
     * @var Mockery\MockInterface|LoggerInterface
     */
    protected $loggerMock;

    /**
     * @var Mockery\MockInterface|RequestInterface
     */
    protected $requestMock;

    /**
     * @var Mockery\MockInterface|ResponseInterface
     */
    protected $responseMock;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建模拟对象
        $this-\u003emailServiceMock = Mockery::mock(MailService::class);
        $this-\u003everifyCodeServiceMock = Mockery::mock(VerifyCodeService::class);
        $this-\u003eloggerMock = Mockery::mock(LoggerInterface::class);
        $this-\u003erequestMock = Mockery::mock(RequestInterface::class);
        $this-\u003eresponseMock = Mockery::mock(ResponseInterface::class);

        // 获取容器并注册mocks
        $container = ApplicationContext::getContainer();
        $container-\u003eset(MailService::class, $this-\u003emailServiceMock);
        $container-\u003eset(VerifyCodeService::class, $this-\u003everifyCodeServiceMock);
        $container-\u003eset(LoggerInterface::class, $this-\u003eloggerMock);
        $container-\u003eset(RequestInterface::class, $this-\u003erequestMock);
        $container-\u003eset(ResponseInterface::class, $this-\u003eresponseMock);

        // 直接创建控制器并设置依赖
        $this-\u003econtroller = new EmailController();
        $reflection = new \ReflectionClass($this-\u003econtroller);
        
        // 设置各个属性
        $mailServiceProperty = $reflection-\u003egetProperty('mailService');
        $mailServiceProperty-\u003esetAccessible(true);
        $mailServiceProperty-\u003esetValue($this-\u003econtroller, $this-\u003emailServiceMock);
        
        $verifyCodeServiceProperty = $reflection-\u003egetProperty('verifyCodeService');
        $verifyCodeServiceProperty-\u003esetAccessible(true);
        $verifyCodeServiceProperty-\u003esetValue($this-\u003econtroller, $this-\u003everifyCodeServiceMock);
        
        $loggerProperty = $reflection-\u003egetProperty('logger');
        $loggerProperty-\u003esetAccessible(true);
        $loggerProperty-\u003esetValue($this-\u003econtroller, $this-\u003eloggerMock);
        
        $requestProperty = $reflection-\u003egetProperty('request');
        $requestProperty-\u003esetAccessible(true);
        $requestProperty-\u003esetValue($this-\u003econtroller, $this-\u003erequestMock);
        
        $responseProperty = $reflection-\u003egetProperty('response');
        $responseProperty-\u003esetAccessible(true);
        $responseProperty-\u003esetValue($this-\u003econtroller, $this-\u003eresponseMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试发送邮件成功
     */
    public function testSendEmailSuccess()
    {
        // 准备测试数据
        $to = 'test@example.com';
        $subject = '测试邮件';
        $template = 'welcome';
        $data = ['username' => '测试用户'];
        $expectedBody = $this-\u003ebuildExpectedWelcomeEmail('测试用户');

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('to')-\u003eandReturn($to);
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('subject')-\u003eandReturn($subject);
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('template')-\u003eandReturn($template);
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('data', [])-\u003eandReturn($data);

        // 模拟服务返回
        $this-\u003emailServiceMock-\u003eshouldReceive('sendSync')
            -\u003ewith($to, $subject, $expectedBody)
            -\u003eandReturn(true);

        // 执行测试
        $result = $this-\u003econtroller-\u003esend($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertEquals('邮件发送成功', $result['message']);
    }

    /**
     * 测试发送邮件失败-缺少必要参数
     */
    public function testSendEmailWithMissingParams()
    {
        // 模拟请求参数（缺少收件人和主题）
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('to')-\u003eandReturn('');
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('subject')-\u003eandReturn('');

        // 执行测试
        $result = $this-\u003econtroller-\u003esend($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(400, $result['code']);
        $this-\u003eassertEquals('缺少必要参数', $result['message']);
    }

    /**
     * 测试发送邮件失败-服务返回false
     */
    public function testSendEmailWithServiceFailure()
    {
        // 准备测试数据
        $to = 'test@example.com';
        $subject = '测试邮件';

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('to')-\u003eandReturn($to);
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('subject')-\u003eandReturn($subject);
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('template')-\u003eandReturn('');
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('data', [])-\u003eandReturn([]);

        // 模拟服务返回
        $this-\u003emailServiceMock-\u003eshouldReceive('sendSync')
            -\u003ewith($to, $subject, '')
            -\u003eandReturn(false);

        // 执行测试
        $result = $this-\u003econtroller-\u003esend($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(500, $result['code']);
        $this-\u003eassertEquals('邮件发送失败', $result['message']);
    }

    /**
     * 测试发送邮件失败-抛出异常
     */
    public function testSendEmailWithException()
    {
        // 准备测试数据
        $to = 'test@example.com';
        $subject = '测试邮件';
        $exceptionMessage = '邮件服务器错误';

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('to')-\u003eandReturn($to);
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('subject')-\u003eandReturn($subject);
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('template')-\u003eandReturn('');
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('data', [])-\u003eandReturn([]);

        // 模拟服务抛出异常
        $this-\u003emailServiceMock-\u003eshouldReceive('sendSync')
            -\u003ewith($to, $subject, '')
            -\u003ethrow(new \Exception($exceptionMessage));

        // 模拟日志记录
        $this-\u003eloggerMock-\u003eshouldReceive('error')
            -\u003ewith('邮件发送异常: ' . $exceptionMessage);

        // 执行测试
        $result = $this-\u003econtroller-\u003esend($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(500, $result['code']);
        $this-\u003eassertEquals('服务器内部错误', $result['message']);
    }

    /**
     * 测试发送验证码成功
     */
    public function testVerifyCodeSuccess()
    {
        // 准备测试数据
        $email = 'test@example.com';
        $serviceResult = [
            'success' => true,
            'message' => '验证码已发送到您的邮箱'
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('email')-\u003eandReturn($email);

        // 模拟服务返回
        $this-\u003everifyCodeServiceMock-\u003eshouldReceive('sendEmailCode')
            -\u003ewith($email)
            -\u003eandReturn($serviceResult);

        // 执行测试
        $result = $this-\u003econtroller-\u003everifyCode($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertEquals('验证码已发送到您的邮箱', $result['message']);
    }

    /**
     * 测试发送验证码失败-邮箱格式不正确
     */
    public function testVerifyCodeWithInvalidEmail()
    {
        // 模拟请求参数（无效邮箱）
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('email')-\u003eandReturn('invalid-email');

        // 执行测试
        $result = $this-\u003econtroller-\u003everifyCode($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(400, $result['code']);
        $this-\u003eassertEquals('邮箱格式不正确', $result['message']);
    }

    /**
     * 测试发送验证码失败-邮箱为空
     */
    public function testVerifyCodeWithEmptyEmail()
    {
        // 模拟请求参数（空邮箱）
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('email')-\u003eandReturn('');

        // 执行测试
        $result = $this-\u003econtroller-\u003everifyCode($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(400, $result['code']);
        $this-\u003eassertEquals('邮箱格式不正确', $result['message']);
    }

    /**
     * 测试发送验证码失败-服务返回失败
     */
    public function testVerifyCodeWithServiceFailure()
    {
        // 准备测试数据
        $email = 'test@example.com';
        $serviceResult = [
            'success' => false,
            'message' => '发送过于频繁，请稍后再试'
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('email')-\u003eandReturn($email);

        // 模拟服务返回
        $this-\u003everifyCodeServiceMock-\u003eshouldReceive('sendEmailCode')
            -\u003ewith($email)
            -\u003eandReturn($serviceResult);

        // 执行测试
        $result = $this-\u003econtroller-\u003everifyCode($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(400, $result['code']);
        $this-\u003eassertEquals('发送过于频繁，请稍后再试', $result['message']);
    }

    /**
     * 测试发送验证码失败-抛出异常
     */
    public function testVerifyCodeWithException()
    {
        // 准备测试数据
        $email = 'test@example.com';
        $exceptionMessage = '发送失败';

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('email')-\u003eandReturn($email);

        // 模拟服务抛出异常
        $this-\u003everifyCodeServiceMock-\u003eshouldReceive('sendEmailCode')
            -\u003ewith($email)
            -\u003ethrow(new \Exception($exceptionMessage));

        // 模拟日志记录
        $this-\u003eloggerMock-\u003eshouldReceive('error')
            -\u003ewith('发送验证码异常: ' . $exceptionMessage);

        // 执行测试
        $result = $this-\u003econtroller-\u003everifyCode($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(500, $result['code']);
        $this-\u003eassertEquals('服务器内部错误', $result['message']);
    }

    /**
     * 构建期望的欢迎邮件内容，用于测试验证
     * @param string $username
     * @return string
     */
    protected function buildExpectedWelcomeEmail($username)
    {
        return <<<HTML
        <h2>欢迎加入个人网站！</h2>
        <p>尊敬的 {$username}：</p>
        <p>欢迎您注册成为我们的会员！</p>
        <p>您的账户已成功创建，您可以开始浏览和使用我们的服务了。</p>
        <p>如有任何问题，请随时联系我们。</p>
        <p>祝您使用愉快！</p>
        HTML;
    }
}