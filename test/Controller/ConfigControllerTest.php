\u003c?php

declare(strict_types=1);

namespace HyperfTest\Controller;

use App\Controller\Api\ConfigController;
use App\Service\SystemService;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Logger\LoggerInterface;
use Hyperf\Testing\TestCase;
use Mockery;

/**
 * ConfigController的单元测试
 * 测试配置控制器的各项功能
 */
class ConfigControllerTest extends TestCase
{
    /**
     * @var ConfigController
     */
    protected $controller;

    /**
     * @var Mockery\MockInterface|SystemService
     */
    protected $systemServiceMock;

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
        $this-\u003esystemServiceMock = Mockery::mock(SystemService::class);
        $this-\u003eloggerMock = Mockery::mock(LoggerInterface::class);
        $this-\u003erequestMock = Mockery::mock(RequestInterface::class);
        $this-\u003eresponseMock = Mockery::mock(ResponseInterface::class);

        // 获取容器并注册mocks
        $container = ApplicationContext::getContainer();
        $container-\u003eset(SystemService::class, $this-\u003esystemServiceMock);
        $container-\u003eset(LoggerInterface::class, $this-\u003eloggerMock);
        $container-\u003eset(RequestInterface::class, $this-\u003erequestMock);
        $container-\u003eset(ResponseInterface::class, $this-\u003eresponseMock);

        // 直接创建控制器并设置依赖
        $this-\u003econtroller = new ConfigController();
        $reflection = new \ReflectionClass($this-\u003econtroller);
        
        // 设置各个属性
        $systemServiceProperty = $reflection-\u003egetProperty('systemService');
        $systemServiceProperty-\u003esetAccessible(true);
        $systemServiceProperty-\u003esetValue($this-\u003econtroller, $this-\u003esystemServiceMock);
        
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
     * 测试获取配置成功
     */
    public function testGetConfigSuccess()
    {
        // 准备测试数据
        $configKey = 'site_name';
        $expectedConfig = '测试网站';

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('key')-\u003eandReturn($configKey);

        // 模拟服务返回
        $this-\u003esystemServiceMock-\u003eshouldReceive('getConfig')
            -\u003ewith($configKey)
            -\u003eandReturn($expectedConfig);

        // 执行测试
        $result = $this-\u003econtroller-\u003egetConfig($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertArrayHasKey('data', $result);
        $this-\u003eassertEquals($expectedConfig, $result['data']);
    }

    /**
     * 测试获取配置失败-抛出异常
     */
    public function testGetConfigWithException()
    {
        // 准备测试数据
        $configKey = 'site_name';
        $exceptionMessage = '配置不存在';

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('key')-\u003eandReturn($configKey);

        // 模拟服务抛出异常
        $this-\u003esystemServiceMock-\u003eshouldReceive('getConfig')
            -\u003ewith($configKey)
            -\u003ethrow(new \Exception($exceptionMessage));

        // 模拟日志记录
        $this-\u003eloggerMock-\u003eshouldReceive('error')
            -\u003ewith('获取配置异常: ' . $exceptionMessage);

        // 执行测试
        $result = $this-\u003econtroller-\u003egetConfig($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(500, $result['code']);
        $this-\u003eassertEquals('获取配置失败', $result['message']);
    }

    /**
     * 测试更新配置成功
     */
    public function testUpdateConfigSuccess()
    {
        // 准备测试数据
        $configKey = 'site_name';
        $configValue = '新网站名称';

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('key')-\u003eandReturn($configKey);
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('value')-\u003eandReturn($configValue);

        // 模拟服务返回
        $this-\u003esystemServiceMock-\u003eshouldReceive('updateConfig')
            -\u003ewith($configKey, $configValue)
            -\u003eandReturn(true);

        // 执行测试
        $result = $this-\u003econtroller-\u003eupdateConfig($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertEquals('配置更新成功', $result['message']);
    }

    /**
     * 测试更新配置失败-配置键为空
     */
    public function testUpdateConfigWithEmptyKey()
    {
        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('key')-\u003eandReturn('');

        // 执行测试
        $result = $this-\u003econtroller-\u003eupdateConfig($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(400, $result['code']);
        $this-\u003eassertEquals('配置键不能为空', $result['message']);
    }

    /**
     * 测试更新配置失败-服务返回false
     */
    public function testUpdateConfigWithServiceFailure()
    {
        // 准备测试数据
        $configKey = 'site_name';
        $configValue = '新网站名称';

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('key')-\u003eandReturn($configKey);
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('value')-\u003eandReturn($configValue);

        // 模拟服务返回
        $this-\u003esystemServiceMock-\u003eshouldReceive('updateConfig')
            -\u003ewith($configKey, $configValue)
            -\u003eandReturn(false);

        // 执行测试
        $result = $this-\u003econtroller-\u003eupdateConfig($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(500, $result['code']);
        $this-\u003eassertEquals('配置更新失败', $result['message']);
    }

    /**
     * 测试更新配置失败-抛出异常
     */
    public function testUpdateConfigWithException()
    {
        // 准备测试数据
        $configKey = 'site_name';
        $configValue = '新网站名称';
        $exceptionMessage = '更新失败';

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('key')-\u003eandReturn($configKey);
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('value')-\u003eandReturn($configValue);

        // 模拟服务抛出异常
        $this-\u003esystemServiceMock-\u003eshouldReceive('updateConfig')
            -\u003ewith($configKey, $configValue)
            -\u003ethrow(new \Exception($exceptionMessage));

        // 模拟日志记录
        $this-\u003eloggerMock-\u003eshouldReceive('error')
            -\u003ewith('更新配置异常: ' . $exceptionMessage);

        // 执行测试
        $result = $this-\u003econtroller-\u003eupdateConfig($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(500, $result['code']);
        $this-\u003eassertEquals('更新配置失败', $result['message']);
    }

    /**
     * 测试批量更新配置成功
     */
    public function testBatchUpdateConfigSuccess()
    {
        // 准备测试数据
        $configs = [
            ['key' => 'site_name', 'value' => '测试网站'],
            ['key' => 'site_desc', 'value' => '网站描述']
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('configs')-\u003eandReturn($configs);

        // 模拟服务返回
        $this-\u003esystemServiceMock-\u003eshouldReceive('batchUpdateConfig')
            -\u003ewith($configs)
            -\u003eandReturn(true);

        // 执行测试
        $result = $this-\u003econtroller-\u003ebatchUpdateConfig($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertEquals('配置批量更新成功', $result['message']);
    }

    /**
     * 测试批量更新配置失败-配置数据为空
     */
    public function testBatchUpdateConfigWithEmptyConfigs()
    {
        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('configs')-\u003eandReturn([]);

        // 执行测试
        $result = $this-\u003econtroller-\u003ebatchUpdateConfig($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(400, $result['code']);
        $this-\u003eassertEquals('配置数据不能为空', $result['message']);
    }

    /**
     * 测试批量更新配置失败-配置数据不是数组
     */
    public function testBatchUpdateConfigWithInvalidConfigsType()
    {
        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('configs')-\u003eandReturn('不是数组');

        // 执行测试
        $result = $this-\u003econtroller-\u003ebatchUpdateConfig($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(400, $result['code']);
        $this-\u003eassertEquals('配置数据不能为空', $result['message']);
    }

    /**
     * 测试批量更新配置失败-服务返回false
     */
    public function testBatchUpdateConfigWithServiceFailure()
    {
        // 准备测试数据
        $configs = [
            ['key' => 'site_name', 'value' => '测试网站']
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('configs')-\u003eandReturn($configs);

        // 模拟服务返回
        $this-\u003esystemServiceMock-\u003eshouldReceive('batchUpdateConfig')
            -\u003ewith($configs)
            -\u003eandReturn(false);

        // 执行测试
        $result = $this-\u003econtroller-\u003ebatchUpdateConfig($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(500, $result['code']);
        $this-\u003eassertEquals('配置批量更新失败', $result['message']);
    }

    /**
     * 测试批量更新配置失败-抛出异常
     */
    public function testBatchUpdateConfigWithException()
    {
        // 准备测试数据
        $configs = [
            ['key' => 'site_name', 'value' => '测试网站']
        ];
        $exceptionMessage = '批量更新失败';

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('configs')-\u003eandReturn($configs);

        // 模拟服务抛出异常
        $this-\u003esystemServiceMock-\u003eshouldReceive('batchUpdateConfig')
            -\u003ewith($configs)
            -\u003ethrow(new \Exception($exceptionMessage));

        // 模拟日志记录
        $this-\u003eloggerMock-\u003eshouldReceive('error')
            -\u003ewith('批量更新配置异常: ' . $exceptionMessage);

        // 执行测试
        $result = $this-\u003econtroller-\u003ebatchUpdateConfig($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(500, $result['code']);
        $this-\u003eassertEquals('批量更新配置失败', $result['message']);
    }
}