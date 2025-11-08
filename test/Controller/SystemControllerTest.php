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

namespace HyperfTest\Controller;

use App\Constants\StatusCode;
use App\Controller\Api\SystemController;
use App\Service\SystemService;
use Exception;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Logger\LoggerInterface;
use Hyperf\Testing\TestCase;
use InvalidArgumentException;
use Mockery;
use ReflectionClass;

/**
 * SystemController的单元测试
 * 测试系统控制器的各项功能.
 * @internal
 * @coversNothing
 */
class SystemControllerTest extends TestCase
{
    /**
     * @var SystemController
     */
    protected $controller;

    /**
     * @var Mockery\MockInterface|SystemService
     */
    protected $systemServiceMock;

    /**
     * @var LoggerInterface|Mockery\MockInterface
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
        $this->systemServiceMock = Mockery::mock(SystemService::class);
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
        $this->requestMock = Mockery::mock(RequestInterface::class);
        $this->responseMock = Mockery::mock(ResponseInterface::class);

        // 获取容器并注册mocks
        $container = ApplicationContext::getContainer();

        // 确保系统服务正确注册
        $container->set(SystemService::class, $this->systemServiceMock);
        $container->set(LoggerInterface::class, $this->loggerMock);
        $container->set(RequestInterface::class, $this->requestMock);
        $container->set(ResponseInterface::class, $this->responseMock);

        // 模拟Response方法
        $this->responseMock->shouldReceive('withHeader')->andReturnSelf();
        $this->responseMock->shouldReceive('withStatus')->andReturnSelf();
        $this->responseMock->shouldReceive('withBody')->andReturnSelf();

        // 直接创建控制器并设置依赖
        $this->controller = new SystemController();
        $reflection = new ReflectionClass($this->controller);

        // 设置systemService属性
        $systemServiceProperty = $reflection->getProperty('systemService');
        $systemServiceProperty->setAccessible(true);
        $systemServiceProperty->setValue($this->controller, $this->systemServiceMock);

        // 设置logger属性
        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($this->controller, $this->loggerMock);

        // 设置response属性
        $responseProperty = $reflection->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($this->controller, $this->responseMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试获取统计数据成功的情况.
     */
    public function testGetStatisticsSuccess()
    {
        // 准备测试数据
        $params = ['type' => 'daily', 'date' => '2023-10-01'];
        $expectedData = [
            'users_count' => 150,
            'blogs_count' => 50,
            'comments_count' => 200,
            'visits_count' => 1000,
        ];

        // 模拟请求参数
        $this->requestMock->shouldReceive('all')->andReturn($params);

        // 模拟服务返回
        $this->systemServiceMock->shouldReceive('getStatistics')
            ->with($params)
            ->andReturn($expectedData);

        // 模拟控制器的success方法
        $this->controller = Mockery::mock(SystemController::class)->shouldAllowMockingProtectedMethods();
        $this->controller->shouldReceive('success')
            ->with($expectedData)
            ->andReturn($this->responseMock);

        // 设置控制器的依赖
        $this->controller->systemService = $this->systemServiceMock;
        $this->controller->logger = $this->loggerMock;

        // 执行测试
        $result = $this->controller->getStatistics($this->requestMock);

        // 验证结果
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    /**
     * 测试获取统计数据成功-复杂统计数据.
     */
    public function testGetStatisticsSuccessWithComplexData()
    {
        // 准备测试数据
        $params = ['type' => 'monthly', 'year' => 2023, 'month' => 10];
        $expectedData = [
            'users_count' => 1500,
            'blogs_count' => 500,
            'comments_count' => 2000,
            'visits_count' => 10000,
            'daily_trend' => [
                ['date' => '2023-10-01', 'visits' => 350],
                ['date' => '2023-10-02', 'visits' => 420],
                ['date' => '2023-10-03', 'visits' => 380],
            ],
            'top_pages' => [
                ['page' => '/home', 'visits' => 5000],
                ['page' => '/about', 'visits' => 3000],
            ],
        ];

        // 模拟请求参数
        $this->requestMock->shouldReceive('all')->andReturn($params);

        // 模拟服务返回
        $this->systemServiceMock->shouldReceive('getStatistics')
            ->with($params)
            ->andReturn($expectedData);

        // 模拟控制器的success方法
        $this->controller = Mockery::mock(SystemController::class)->shouldAllowMockingProtectedMethods();
        $this->controller->shouldReceive('success')
            ->with($expectedData)
            ->andReturn($this->responseMock);

        // 设置控制器的依赖
        $this->controller->systemService = $this->systemServiceMock;
        $this->controller->logger = $this->loggerMock;

        // 执行测试
        $result = $this->controller->getStatistics($this->requestMock);

        // 验证结果
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    /**
     * 测试获取统计数据失败的情况-数据库连接错误.
     */
    public function testGetStatisticsFailure()
    {
        // 准备测试数据
        $params = ['type' => 'monthly'];
        $exceptionMessage = '数据库连接失败';

        // 模拟请求参数
        $this->requestMock->shouldReceive('all')->andReturn($params);

        // 模拟服务抛出异常
        $exception = new Exception($exceptionMessage);
        $this->systemServiceMock->shouldReceive('getStatistics')
            ->with($params)
            ->andThrow($exception);

        // 验证日志调用
        $this->loggerMock->shouldReceive('error')
            ->with('获取统计数据异常: ' . $exceptionMessage)
            ->andReturnNull();

        // 模拟控制器的fail方法
        $this->controller = Mockery::mock(SystemController::class)->shouldAllowMockingProtectedMethods();
        $this->controller->shouldReceive('fail')
            ->with(StatusCode::INTERNAL_SERVER_ERROR, '获取统计数据失败')
            ->andReturn($this->responseMock);

        // 设置控制器的依赖
        $this->controller->systemService = $this->systemServiceMock;
        $this->controller->logger = $this->loggerMock;

        // 执行测试
        $result = $this->controller->getStatistics($this->requestMock);

        // 验证结果
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    /**
     * 测试获取统计数据失败的情况-无效参数异常.
     */
    public function testGetStatisticsWithInvalidParamsException()
    {
        // 准备测试数据
        $params = ['type' => 'daily', 'date' => 'invalid-date'];
        $exceptionMessage = '日期格式无效';

        // 模拟请求参数
        $this->requestMock->shouldReceive('all')->andReturn($params);

        // 模拟服务抛出异常
        $exception = new InvalidArgumentException($exceptionMessage);
        $this->systemServiceMock->shouldReceive('getStatistics')
            ->with($params)
            ->andThrow($exception);

        // 验证日志调用
        $this->loggerMock->shouldReceive('error')
            ->with('获取统计数据异常: ' . $exceptionMessage)
            ->andReturnNull();

        // 模拟控制器的fail方法
        $this->controller = Mockery::mock(SystemController::class)->shouldAllowMockingProtectedMethods();
        $this->controller->shouldReceive('fail')
            ->with(StatusCode::INTERNAL_SERVER_ERROR, '获取统计数据失败')
            ->andReturn($this->responseMock);

        // 设置控制器的依赖
        $this->controller->systemService = $this->systemServiceMock;
        $this->controller->logger = $this->loggerMock;

        // 执行测试
        $result = $this->controller->getStatistics($this->requestMock);

        // 验证结果
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    /**
     * 测试获取统计数据-空参数情况.
     */
    public function testGetStatisticsWithEmptyParams()
    {
        // 准备测试数据
        $params = [];
        $expectedData = [
            'users_count' => 0,
            'blogs_count' => 0,
            'comments_count' => 0,
            'visits_count' => 0,
        ];

        // 模拟请求参数
        $this->requestMock->shouldReceive('all')->andReturn($params);

        // 模拟服务返回
        $this->systemServiceMock->shouldReceive('getStatistics')
            ->with($params)
            ->andReturn($expectedData);

        // 模拟控制器的success方法
        $this->controller = Mockery::mock(SystemController::class)->shouldAllowMockingProtectedMethods();
        $this->controller->shouldReceive('success')
            ->with($expectedData)
            ->andReturn($this->responseMock);

        // 设置控制器的依赖
        $this->controller->systemService = $this->systemServiceMock;
        $this->controller->logger = $this->loggerMock;

        // 执行测试
        $result = $this->controller->getStatistics($this->requestMock);

        // 验证结果
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }
}
