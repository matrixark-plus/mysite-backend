\u003c?php

declare(strict_types=1);

namespace HyperfTest\Controller;

use App\Controller\Api\MindMapController;
use App\Service\MindMapService;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Logger\LoggerInterface;
use Hyperf\Testing\TestCase;
use Mockery;

/**
 * MindMapController的单元测试
 * 测试脑图控制器的各项功能
 */
class MindMapControllerTest extends TestCase
{
    /**
     * @var MindMapController
     */
    protected $controller;

    /**
     * @var Mockery\MockInterface|MindMapService
     */
    protected $mindMapServiceMock;

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
        $this-\u003emindMapServiceMock = Mockery::mock(MindMapService::class);
        $this-\u003eloggerMock = Mockery::mock(LoggerInterface::class);
        $this-\u003erequestMock = Mockery::mock(RequestInterface::class);
        $this-\u003eresponseMock = Mockery::mock(ResponseInterface::class);

        // 获取容器并注册mocks
        $container = ApplicationContext::getContainer();
        $container-\u003eset(MindMapService::class, $this-\u003emindMapServiceMock);
        $container-\u003eset(LoggerInterface::class, $this-\u003eloggerMock);
        $container-\u003eset(RequestInterface::class, $this-\u003erequestMock);
        $container-\u003eset(ResponseInterface::class, $this-\u003eresponseMock);

        // 直接创建控制器并设置依赖
        $this-\u003econtroller = new MindMapController();
        $reflection = new \ReflectionClass($this-\u003econtroller);
        
        // 设置各个属性
        $mindMapServiceProperty = $reflection-\u003egetProperty('mindMapService');
        $mindMapServiceProperty-\u003esetAccessible(true);
        $mindMapServiceProperty-\u003esetValue($this-\u003econtroller, $this-\u003emindMapServiceMock);
        
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
     * 测试获取根节点列表成功
     */
    public function testGetRootNodesSuccess()
    {
        // 准备测试数据
        $params = ['page' => 1, 'limit' => 10];
        $expectedResult = [
            'total' => 5,
            'list' => [
                ['id' => 1, 'title' => '脑图1'],
                ['id' => 2, 'title' => '脑图2']
            ]
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($params);

        // 模拟服务返回
        $this-\u003emindMapServiceMock-\u003eshouldReceive('getRootNodes')
            -\u003ewith($params)
            -\u003eandReturn($expectedResult);

        // 执行测试
        $result = $this-\u003econtroller-\u003 egetRootNodes($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertEquals($expectedResult, $result['data']);
    }

    /**
     * 测试获取根节点列表失败-抛出异常
     */
    public function testGetRootNodesWithException()
    {
        // 准备测试数据
        $params = [];
        $exceptionMessage = '数据库查询错误';

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($params);

        // 模拟服务抛出异常
        $this-\u003emindMapServiceMock-\u003eshouldReceive('getRootNodes')
            -\u003ewith($params)
            -\u003ethrow(new \Exception($exceptionMessage));

        // 执行测试
        $result = $this-\u003econtroller-\u003 egetRootNodes($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(500, $result['code']);
        $this-\u003eassertEquals('获取脑图列表失败', $result['message']);
    }

    /**
     * 测试获取脑图数据成功-不包含内容
     */
    public function testGetMindMapDataSuccessWithoutContent()
    {
        // 准备测试数据
        $id = 1;
        $expectedResult = [
            'id' => 1,
            'title' => '测试脑图',
            'nodes' => [
                ['id' => 101, 'title' => '节点1'],
                ['id' => 102, 'title' => '节点2']
            ]
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('include_content', false)-\u003eandReturn(false);

        // 模拟服务返回
        $this-\u003emindMapServiceMock-\u003eshouldReceive('getMindMapData')
            -\u003ewith($id, false)
            -\u003eandReturn($expectedResult);

        // 执行测试
        $result = $this-\u003econtroller-\u003 egetMindMapData($id, $this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertEquals($expectedResult, $result['data']);
    }

    /**
     * 测试获取脑图数据成功-包含内容
     */
    public function testGetMindMapDataSuccessWithContent()
    {
        // 准备测试数据
        $id = 1;
        $expectedResult = [
            'id' => 1,
            'title' => '测试脑图',
            'content' => '脑图详情内容',
            'nodes' => [
                ['id' => 101, 'title' => '节点1', 'content' => '节点1内容'],
                ['id' => 102, 'title' => '节点2', 'content' => '节点2内容']
            ]
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('include_content', false)-\u003eandReturn(true);

        // 模拟服务返回
        $this-\u003emindMapServiceMock-\u003eshouldReceive('getMindMapData')
            -\u003ewith($id, true)
            -\u003eandReturn($expectedResult);

        // 执行测试
        $result = $this-\u003econtroller-\u003 egetMindMapData($id, $this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertEquals($expectedResult, $result['data']);
    }

    /**
     * 测试获取脑图数据失败-脑图不存在
     */
    public function testGetMindMapDataNotFound()
    {
        // 准备测试数据
        $id = 999;
        $exceptionMessage = '脑图不存在';

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('include_content', false)-\u003eandReturn(false);

        // 模拟服务抛出异常
        $this-\u003emindMapServiceMock-\u003eshouldReceive('getMindMapData')
            -\u003ewith($id, false)
            -\u003ethrow(new \Exception($exceptionMessage));

        // 执行测试
        $result = $this-\u003econtroller-\u003 egetMindMapData($id, $this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(404, $result['code']);
        $this-\u003eassertEquals($exceptionMessage, $result['message']);
    }

    /**
     * 测试获取脑图数据失败-抛出异常且无消息
     */
    public function testGetMindMapDataWithEmptyExceptionMessage()
    {
        // 准备测试数据
        $id = 999;

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('include_content', false)-\u003eandReturn(false);

        // 模拟服务抛出异常
        $this-\u003emindMapServiceMock-\u003eshouldReceive('getMindMapData')
            -\u003ewith($id, false)
            -\u003ethrow(new \Exception(''));

        // 执行测试
        $result = $this-\u003econtroller-\u003 egetMindMapData($id, $this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(404, $result['code']);
        $this-\u003eassertEquals('脑图不存在', $result['message']);
    }

    /**
     * 测试获取脑图数据失败-数据库错误
     */
    public function testGetMindMapDataWithDatabaseError()
    {
        // 准备测试数据
        $id = 1;
        $exceptionMessage = '数据库连接错误';

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('include_content', false)-\u003eandReturn(false);

        // 模拟服务抛出异常
        $this-\u003emindMapServiceMock-\u003eshouldReceive('getMindMapData')
            -\u003ewith($id, false)
            -\u003ethrow(new \Exception($exceptionMessage));

        // 执行测试
        $result = $this-\u003econtroller-\u003 egetMindMapData($id, $this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(404, $result['code']);
        $this-\u003eassertEquals($exceptionMessage, $result['message']);
    }
}