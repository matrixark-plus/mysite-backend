\u003c?php

declare(strict_types=1);

namespace HyperfTest\Controller;

use App\Controller\Api\SocialShareController;
use App\Service\SocialShareService;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Logger\LoggerInterface;
use Hyperf\Testing\TestCase;
use Mockery;

/**
 * SocialShareController的单元测试
 * 测试社交分享控制器的各项功能
 */
class SocialShareControllerTest extends TestCase
{
    /**
     * @var SocialShareController
     */
    protected $controller;

    /**
     * @var Mockery\MockInterface|SocialShareService
     */
    protected $socialShareServiceMock;

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
        $this-\u003esocialShareServiceMock = Mockery::mock(SocialShareService::class);
        $this-\u003eloggerMock = Mockery::mock(LoggerInterface::class);
        $this-\u003erequestMock = Mockery::mock(RequestInterface::class);
        $this-\u003eresponseMock = Mockery::mock(ResponseInterface::class);

        // 获取容器并注册mocks
        $container = ApplicationContext::getContainer();
        $container-\u003eset(SocialShareService::class, $this-\u003esocialShareServiceMock);
        $container-\u003eset(LoggerInterface::class, $this-\u003eloggerMock);
        $container-\u003eset(RequestInterface::class, $this-\u003erequestMock);
        $container-\u003eset(ResponseInterface::class, $this-\u003eresponseMock);

        // 直接创建控制器并设置依赖
        $this-\u003econtroller = new SocialShareController();
        $reflection = new \ReflectionClass($this-\u003econtroller);
        
        // 设置各个属性
        $socialShareServiceProperty = $reflection-\u003egetProperty('socialShareService');
        $socialShareServiceProperty-\u003esetAccessible(true);
        $socialShareServiceProperty-\u003esetValue($this-\u003econtroller, $this-\u003esocialShareServiceMock);
        
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
     * 测试获取分享配置成功
     */
    public function testGetShareConfigSuccess()
    {
        // 准备测试数据
        $expectedConfig = [
            'wechat' => [
                'app_id' => 'wx1234567890',
                'enabled' => true
            ],
            'qq' => [
                'app_id' => '10123456',
                'enabled' => true
            ],
            'weibo' => [
                'app_id' => '2345678901',
                'enabled' => true
            ],
            'title' => '分享标题',
            'description' => '分享描述',
            'image' => 'https://example.com/share.jpg'
        ];

        // 模拟服务返回
        $this-\u003esocialShareServiceMock-\u003eshouldReceive('getShareConfig')
            -\u003eandReturn($expectedConfig);

        // 执行测试
        $result = $this-\u003econtroller-\u003 egetShareConfig();

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertEquals($expectedConfig, $result['data']);
    }

    /**
     * 测试获取分享配置成功-返回空配置
     */
    public function testGetShareConfigSuccessWithEmptyConfig()
    {
        // 准备测试数据
        $expectedConfig = [];

        // 模拟服务返回
        $this-\u003esocialShareServiceMock-\u003eshouldReceive('getShareConfig')
            -\u003eandReturn($expectedConfig);

        // 执行测试
        $result = $this-\u003econtroller-\u003 egetShareConfig();

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertEquals($expectedConfig, $result['data']);
    }

    /**
     * 测试获取分享配置成功-返回部分配置
     */
    public function testGetShareConfigSuccessWithPartialConfig()
    {
        // 准备测试数据
        $expectedConfig = [
            'wechat' => [
                'app_id' => 'wx1234567890',
                'enabled' => false
            ],
            'weibo' => [
                'app_id' => '2345678901',
                'enabled' => true
            ],
            'title' => '分享标题'
        ];

        // 模拟服务返回
        $this-\u003esocialShareServiceMock-\u003eshouldReceive('getShareConfig')
            -\u003eandReturn($expectedConfig);

        // 执行测试
        $result = $this-\u003econtroller-\u003 egetShareConfig();

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertEquals($expectedConfig, $result['data']);
    }

    /**
     * 测试获取分享配置失败-抛出异常
     */
    public function testGetShareConfigWithException()
    {
        // 准备测试数据
        $exceptionMessage = '配置文件读取失败';

        // 模拟服务抛出异常
        $this-\u003esocialShareServiceMock-\u003eshouldReceive('getShareConfig')
            -\u003ethrow(new \Exception($exceptionMessage));

        // 执行测试
        $result = $this-\u003econtroller-\u003 egetShareConfig();

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(500, $result['code']);
        $this-\u003eassertEquals('服务器内部错误', $result['message']);
    }

    /**
     * 测试获取分享配置失败-抛出RuntimeException
     */
    public function testGetShareConfigWithRuntimeException()
    {
        // 准备测试数据
        $exceptionMessage = '服务暂时不可用';

        // 模拟服务抛出异常
        $this-\u003esocialShareServiceMock-\u003eshouldReceive('getShareConfig')
            -\u003ethrow(new \RuntimeException($exceptionMessage));

        // 执行测试
        $result = $this-\u003econtroller-\u003 egetShareConfig();

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(500, $result['code']);
        $this-\u003eassertEquals('服务器内部错误', $result['message']);
    }
}