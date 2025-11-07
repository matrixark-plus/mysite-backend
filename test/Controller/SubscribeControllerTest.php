\u003c?php

declare(strict_types=1);

namespace HyperfTest\Controller;

use App\Controller\Api\SubscribeController;
use App\Service\SubscribeService;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Logger\LoggerInterface;
use Hyperf\Testing\TestCase;
use Mockery;

/**
 * SubscribeController的单元测试
 * 测试订阅控制器的各项功能
 */
class SubscribeControllerTest extends TestCase
{
    /**
     * @var SubscribeController
     */
    protected $controller;

    /**
     * @var Mockery\MockInterface|SubscribeService
     */
    protected $subscribeServiceMock;

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
        $this-\u003esubscribeServiceMock = Mockery::mock(SubscribeService::class);
        $this-\u003eloggerMock = Mockery::mock(LoggerInterface::class);
        $this-\u003erequestMock = Mockery::mock(RequestInterface::class);
        $this-\u003eresponseMock = Mockery::mock(ResponseInterface::class);

        // 获取容器并注册mocks
        $container = ApplicationContext::getContainer();
        $container-\u003eset(SubscribeService::class, $this-\u003esubscribeServiceMock);
        $container-\u003eset(LoggerInterface::class, $this-\u003eloggerMock);
        $container-\u003eset(RequestInterface::class, $this-\u003erequestMock);
        $container-\u003eset(ResponseInterface::class, $this-\u003eresponseMock);

        // 直接创建控制器并设置依赖
        $this-\u003econtroller = new SubscribeController();
        $reflection = new \ReflectionClass($this-\u003econtroller);
        
        // 设置各个属性
        $subscribeServiceProperty = $reflection-\u003egetProperty('subscribeService');
        $subscribeServiceProperty-\u003esetAccessible(true);
        $subscribeServiceProperty-\u003esetValue($this-\u003econtroller, $this-\u003esubscribeServiceMock);
        
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
     * 测试博客订阅成功
     */
    public function testBlogSubscribeSuccess()
    {
        // 准备测试数据
        $email = 'test@example.com';
        $serviceResult = [
            'success' => true,
            'message' => '订阅成功，请查收邮件进行确认'
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('email')-\u003eandReturn($email);

        // 模拟服务返回
        $this-\u003esubscribeServiceMock-\u003eshouldReceive('addBlogSubscribe')
            -\u003ewith($email)
            -\u003eandReturn($serviceResult);

        // 执行测试
        $result = $this-\u003econtroller-\u003eblogSubscribe($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertEquals('订阅成功，请查收邮件进行确认', $result['message']);
    }

    /**
     * 测试博客订阅失败-邮箱格式不正确
     */
    public function testBlogSubscribeWithInvalidEmail()
    {
        // 模拟请求参数（无效邮箱）
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('email')-\u003eandReturn('invalid-email');

        // 执行测试
        $result = $this-\u003econtroller-\u003eblogSubscribe($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(400, $result['code']);
        $this-\u003eassertEquals('邮箱格式不正确', $result['message']);
    }

    /**
     * 测试博客订阅失败-邮箱为空
     */
    public function testBlogSubscribeWithEmptyEmail()
    {
        // 模拟请求参数（空邮箱）
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('email')-\u003eandReturn('');

        // 执行测试
        $result = $this-\u003econtroller-\u003eblogSubscribe($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(400, $result['code']);
        $this-\u003eassertEquals('邮箱格式不正确', $result['message']);
    }

    /**
     * 测试博客订阅失败-服务返回失败
     */
    public function testBlogSubscribeWithServiceFailure()
    {
        // 准备测试数据
        $email = 'test@example.com';
        $serviceResult = [
            'success' => false,
            'message' => '该邮箱已经订阅过了'
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('email')-\u003eandReturn($email);

        // 模拟服务返回
        $this-\u003esubscribeServiceMock-\u003eshouldReceive('addBlogSubscribe')
            -\u003ewith($email)
            -\u003eandReturn($serviceResult);

        // 执行测试
        $result = $this-\u003econtroller-\u003eblogSubscribe($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(400, $result['code']);
        $this-\u003eassertEquals('该邮箱已经订阅过了', $result['message']);
    }

    /**
     * 测试博客订阅失败-抛出异常
     */
    public function testBlogSubscribeWithException()
    {
        // 准备测试数据
        $email = 'test@example.com';
        $exceptionMessage = '数据库操作失败';

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('email')-\u003eandReturn($email);

        // 模拟服务抛出异常
        $this-\u003esubscribeServiceMock-\u003eshouldReceive('addBlogSubscribe')
            -\u003ewith($email)
            -\u003ethrow(new \Exception($exceptionMessage));

        // 执行测试
        $result = $this-\u003econtroller-\u003eblogSubscribe($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(500, $result['code']);
        $this-\u003eassertEquals('服务器内部错误', $result['message']);
    }

    /**
     * 测试确认订阅成功
     */
    public function testConfirmSubscribeSuccess()
    {
        // 准备测试数据
        $token = 'valid_token_123';
        $serviceResult = [
            'success' => true,
            'message' => '订阅确认成功！感谢您的订阅。'
        ];
        $expectedHtml = $this-\u003ebuildExpectedConfirmPage(true, '订阅确认成功！感谢您的订阅。');

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('token')-\u003eandReturn($token);

        // 模拟服务返回
        $this-\u003esubscribeServiceMock-\u003eshouldReceive('confirmSubscribe')
            -\u003ewith($token)
            -\u003eandReturn($serviceResult);

        // 模拟响应对象
        $this-\u003eresponseMock-\u003eshouldReceive('raw')
            -\u003ewith($expectedHtml)
            -\u003eandReturn($this-\u003eresponseMock);

        $this-\u003eresponseMock-\u003eshouldReceive('withHeader')
            -\u003ewith('Content-Type', 'text/html')
            -\u003eandReturn('success_response');

        // 执行测试
        $result = $this-\u003econtroller-\u003econfirmSubscribe($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertEquals('success_response', $result);
    }

    /**
     * 测试确认订阅失败-缺少token
     */
    public function testConfirmSubscribeWithMissingToken()
    {
        // 模拟请求参数（空token）
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('token')-\u003eandReturn('');

        // 执行测试
        $result = $this-\u003econtroller-\u003econfirmSubscribe($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(400, $result['code']);
        $this-\u003eassertEquals('缺少确认参数', $result['message']);
    }

    /**
     * 测试确认订阅失败-token无效
     */
    public function testConfirmSubscribeWithInvalidToken()
    {
        // 准备测试数据
        $token = 'invalid_token_123';
        $serviceResult = [
            'success' => false,
            'message' => '订阅链接无效或已过期'
        ];
        $expectedHtml = $this-\u003ebuildExpectedConfirmPage(false, '订阅链接无效或已过期');

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('token')-\u003eandReturn($token);

        // 模拟服务返回
        $this-\u003esubscribeServiceMock-\u003eshouldReceive('confirmSubscribe')
            -\u003ewith($token)
            -\u003eandReturn($serviceResult);

        // 模拟响应对象
        $this-\u003eresponseMock-\u003eshouldReceive('raw')
            -\u003ewith($expectedHtml)
            -\u003eandReturn($this-\u003eresponseMock);

        $this-\u003eresponseMock-\u003eshouldReceive('withHeader')
            -\u003ewith('Content-Type', 'text/html')
            -\u003eandReturn('error_response');

        // 执行测试
        $result = $this-\u003econtroller-\u003econfirmSubscribe($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertEquals('error_response', $result);
    }

    /**
     * 测试确认订阅失败-抛出异常
     */
    public function testConfirmSubscribeWithException()
    {
        // 准备测试数据
        $token = 'valid_token_123';
        $exceptionMessage = '数据库连接错误';
        $expectedHtml = $this-\u003ebuildExpectedConfirmPage(false, '服务器内部错误，请稍后重试');

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('token')-\u003eandReturn($token);

        // 模拟服务抛出异常
        $this-\u003esubscribeServiceMock-\u003eshouldReceive('confirmSubscribe')
            -\u003ewith($token)
            -\u003ethrow(new \Exception($exceptionMessage));

        // 模拟响应对象
        $this-\u003eresponseMock-\u003eshouldReceive('raw')
            -\u003ewith($expectedHtml)
            -\u003eandReturn($this-\u003eresponseMock);

        $this-\u003eresponseMock-\u003eshouldReceive('withHeader')
            -\u003ewith('Content-Type', 'text/html')
            -\u003eandReturn('exception_response');

        // 执行测试
        $result = $this-\u003econtroller-\u003econfirmSubscribe($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertEquals('exception_response', $result);
    }

    /**
     * 构建期望的确认页面HTML，用于测试验证
     * @param bool $success 是否成功
     * @param string $message 消息
     * @return string
     */
    protected function buildExpectedConfirmPage($success, $message)
    {
        $title = $success ? '订阅成功' : '订阅失败';
        $color = $success ? '#00ff99' : '#ff6600';
        
        return <<<HTML
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$title}</title>
            <style>
                body {
                    font-family: 'Microsoft YaHei', Arial, sans-serif;
                    background-color: #1a1a1a;
                    color: #ffffff;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                    text-align: center;
                }
                .container {
                    max-width: 600px;
                    padding: 40px;
                    border-radius: 8px;
                    background-color: #2a2a2a;
                }
                h1 {
                    color: {$color};
                    font-size: 32px;
                    margin-bottom: 20px;
                }
                p {
                    font-size: 18px;
                    line-height: 1.6;
                }
                .btn {
                    display: inline-block;
                    margin-top: 30px;
                    padding: 12px 30px;
                    background-color: #003366;
                    color: #ffffff;
                    text-decoration: none;
                    border-radius: 4px;
                    font-size: 16px;
                    transition: background-color 0.3s;
                }
                .btn:hover {
                    background-color: #004080;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>{$title}</h1>
                <p>{$message}</p>
                <a href="/" class="btn">返回网站首页</a>
            </div>
        </body>
        </html>
        HTML;
    }
}