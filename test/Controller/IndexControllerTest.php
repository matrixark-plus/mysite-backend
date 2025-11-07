<?php

declare(strict_types=1);

namespace HyperfTest\Controller;

use App\Controller\IndexController;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Testing\TestCase;
use Mockery;

/**
 * IndexController的单元测试
 * 测试首页控制器的功能
 */
class IndexControllerTest extends TestCase
{
    /**
     * @var IndexController
     */
    protected $controller;

    /**
     * @var Mockery\MockInterface|RequestInterface
     */
    protected $requestMock;

    /**
     * @var Mockery\MockInterface|ResponseInterface
     */
    protected $responseMock;

    /**
     * 设置测试环境
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 创建模拟对象
        $this->requestMock = Mockery::mock(RequestInterface::class);
        $this->responseMock = Mockery::mock(ResponseInterface::class);

        // 获取容器并注册mocks
        $container = ApplicationContext::getContainer();
        $container->set(RequestInterface::class, $this->requestMock);
        $container->set(ResponseInterface::class, $this->responseMock);

        // 创建控制器实例
        $this->controller = new IndexController();
        $reflection = new \ReflectionClass($this->controller);

        // 设置request属性
        $requestProperty = $reflection->getProperty('request');
        $requestProperty->setAccessible(true);
        $requestProperty->setValue($this->controller, $this->requestMock);

        // 设置response属性
        $responseProperty = $reflection->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($this->controller, $this->responseMock);
    }

    /**
     * 清理测试环境
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试index方法使用默认参数
     */
    public function testIndexWithDefaultUser()
    {
        // 模拟请求参数
        $this->requestMock->shouldReceive('input')
            ->with('user', 'Hyperf')
            ->andReturn('Hyperf');
        
        $this->requestMock->shouldReceive('getMethod')
            ->andReturn('GET');

        // 执行测试
        $result = $this->controller->index();

        // 验证结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('method', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('GET', $result['method']);
        $this->assertEquals('Hello Hyperf.', $result['message']);
    }

    /**
     * 测试index方法使用自定义用户参数
     */
    public function testIndexWithCustomUser()
    {
        // 模拟请求参数
        $customUser = 'TestUser';
        $this->requestMock->shouldReceive('input')
            ->with('user', 'Hyperf')
            ->andReturn($customUser);
        
        $this->requestMock->shouldReceive('getMethod')
            ->andReturn('POST');

        // 执行测试
        $result = $this->controller->index();

        // 验证结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('method', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('POST', $result['method']);
        $this->assertEquals('Hello TestUser.', $result['message']);
    }

    /**
     * 测试index方法使用空用户参数
     */
    public function testIndexWithEmptyUser()
    {
        // 模拟请求参数
        $this->requestMock->shouldReceive('input')
            ->with('user', 'Hyperf')
            ->andReturn('');
        
        $this->requestMock->shouldReceive('getMethod')
            ->andReturn('PUT');

        // 执行测试
        $result = $this->controller->index();

        // 验证结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('method', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('PUT', $result['method']);
        $this->assertEquals('Hello .', $result['message']);
    }

    /**
     * 测试index方法使用特殊字符用户参数
     */
    public function testIndexWithSpecialCharsUser()
    {
        // 模拟请求参数
        $specialUser = 'User!@#$%^&*()';
        $this->requestMock->shouldReceive('input')
            ->with('user', 'Hyperf')
            ->andReturn($specialUser);
        
        $this->requestMock->shouldReceive('getMethod')
            ->andReturn('DELETE');

        // 执行测试
        $result = $this->controller->index();

        // 验证结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('method', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('DELETE', $result['method']);
        $this->assertEquals('Hello User!@#$%^&*().', $result['message']);
    }

    /**
     * 测试index方法使用非GET请求方法
     */
    public function testIndexWithDifferentHttpMethods()
    {
        // 测试不同的HTTP方法
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        
        foreach ($methods as $method) {
            // 重置mock期望
            $this->requestMock = Mockery::mock(RequestInterface::class);
            $reflection = new \ReflectionClass($this->controller);
            $requestProperty = $reflection->getProperty('request');
            $requestProperty->setAccessible(true);
            $requestProperty->setValue($this->controller, $this->requestMock);
            
            // 设置模拟行为
            $this->requestMock->shouldReceive('input')
                ->with('user', 'Hyperf')
                ->andReturn('TestUser');
            
            $this->requestMock->shouldReceive('getMethod')
                ->andReturn($method);

            // 执行测试
            $result = $this->controller->index();

            // 验证结果
            $this->assertIsArray($result);
            $this->assertEquals($method, $result['method']);
            $this->assertEquals('Hello TestUser.', $result['message']);
        }
    }
}