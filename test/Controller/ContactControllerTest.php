\u003c?php

declare(strict_types=1);

namespace HyperfTest\Controller;

use App\Controller\Api\ContactController;
use App\Service\ContactService;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Logger\LoggerInterface;
use Hyperf\Testing\TestCase;
use Mockery;

/**
 * ContactController的单元测试
 * 测试联系表单控制器的功能
 */
class ContactControllerTest extends TestCase
{
    /**
     * @var ContactController
     */
    protected $controller;

    /**
     * @var Mockery\MockInterface|ContactService
     */
    protected $contactServiceMock;

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
        $this-\u003econtactServiceMock = Mockery::mock(ContactService::class);
        $this-\u003eloggerMock = Mockery::mock(LoggerInterface::class);
        $this-\u003erequestMock = Mockery::mock(RequestInterface::class);
        $this-\u003eresponseMock = Mockery::mock(ResponseInterface::class);

        // 获取容器并注册mocks
        $container = ApplicationContext::getContainer();
        $container-\u003eset(ContactService::class, $this-\u003econtactServiceMock);
        $container-\u003eset(LoggerInterface::class, $this-\u003eloggerMock);
        $container-\u003eset(RequestInterface::class, $this-\u003erequestMock);
        $container-\u003eset(ResponseInterface::class, $this-\u003eresponseMock);

        // 直接创建控制器并设置依赖
        $this-\u003econtroller = new ContactController();
        $reflection = new \ReflectionClass($this-\u003econtroller);
        
        // 设置各个属性
        $contactServiceProperty = $reflection-\u003egetProperty('contactService');
        $contactServiceProperty-\u003esetAccessible(true);
        $contactServiceProperty-\u003esetValue($this-\u003econtroller, $this-\u003econtactServiceMock);
        
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
     * 测试提交联系表单成功
     */
    public function testSubmitContactSuccess()
    {
        // 准备测试数据
        $formData = [
            'name' => '张三',
            'email' => 'zhangsan@example.com',
            'phone' => '13800138000',
            'message' => '这是一条测试消息'
        ];
        $clientIp = '192.168.1.1';
        $serviceResult = [
            'success' => true,
            'message' => '提交成功，感谢您的留言'
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($formData);
        $this-\u003erequestMock-\u003eshouldReceive('getServerParams')-\u003eandReturn(['remote_addr' => $clientIp]);

        // 模拟服务返回
        $this-\u003econtactServiceMock-\u003eshouldReceive('submitContactForm')
            -\u003ewith(array_merge($formData, ['ip' => $clientIp]))
            -\u003eandReturn($serviceResult);

        // 执行测试
        $result = $this-\u003econtroller-\u003esubmitContact($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertEquals('提交成功，感谢您的留言', $result['message']);
    }

    /**
     * 测试提交联系表单失败-参数验证失败
     */
    public function testSubmitContactWithValidationFailure()
    {
        // 准备测试数据
        $formData = [
            'name' => '',
            'email' => 'invalid-email',
            'message' => '消息太短'
        ];
        $clientIp = '192.168.1.1';
        $serviceResult = [
            'success' => false,
            'message' => '请输入有效的姓名、邮箱和消息内容'
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($formData);
        $this-\u003erequestMock-\u003eshouldReceive('getServerParams')-\u003eandReturn(['remote_addr' => $clientIp]);

        // 模拟服务返回
        $this-\u003econtactServiceMock-\u003eshouldReceive('submitContactForm')
            -\u003ewith(array_merge($formData, ['ip' => $clientIp]))
            -\u003eandReturn($serviceResult);

        // 执行测试
        $result = $this-\u003econtroller-\u003esubmitContact($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(400, $result['code']);
        $this-\u003eassertEquals('请输入有效的姓名、邮箱和消息内容', $result['message']);
    }

    /**
     * 测试提交联系表单-客户端IP不存在
     */
    public function testSubmitContactWithoutClientIp()
    {
        // 准备测试数据
        $formData = [
            'name' => '张三',
            'email' => 'zhangsan@example.com',
            'message' => '测试消息'
        ];
        $serviceResult = [
            'success' => true,
            'message' => '提交成功'
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($formData);
        $this-\u003erequestMock-\u003eshouldReceive('getServerParams')-\u003eandReturn([]);

        // 模拟服务返回
        $this-\u003econtactServiceMock-\u003eshouldReceive('submitContactForm')
            -\u003ewith(array_merge($formData, ['ip' => '']))
            -\u003eandReturn($serviceResult);

        // 执行测试
        $result = $this-\u003econtroller-\u003esubmitContact($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
    }

    /**
     * 测试提交联系表单失败-抛出异常
     */
    public function testSubmitContactWithException()
    {
        // 准备测试数据
        $formData = [
            'name' => '张三',
            'email' => 'zhangsan@example.com',
            'message' => '测试消息'
        ];
        $clientIp = '192.168.1.1';
        $exceptionMessage = '数据库错误';

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($formData);
        $this-\u003erequestMock-\u003eshouldReceive('getServerParams')-\u003eandReturn(['remote_addr' => $clientIp]);

        // 模拟服务抛出异常
        $this-\u003econtactServiceMock-\u003eshouldReceive('submitContactForm')
            -\u003ewith(array_merge($formData, ['ip' => $clientIp]))
            -\u003ethrow(new \Exception($exceptionMessage));

        // 模拟日志记录
        $this-\u003eloggerMock-\u003eshouldReceive('error')
            -\u003ewith('提交联系表单异常: ' . $exceptionMessage);

        // 执行测试
        $result = $this-\u003econtroller-\u003esubmitContact($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(500, $result['code']);
        $this-\u003eassertEquals('服务器内部错误', $result['message']);
    }
}