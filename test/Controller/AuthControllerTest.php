<?php

declare(strict_types=1);

namespace HyperfTest\Controller;

use App\Controller\Api\AuthController;
use App\Service\UserService;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Logger\LoggerInterface;
use Hyperf\Testing\TestCase;
use Mockery;
use Qbhy\HyperfAuth\AuthManager;

/**
 * AuthController的单元测试
 * 测试认证控制器的各项功能
 */
class AuthControllerTest extends TestCase
{
    /**
     * @var AuthController
     */
    protected $controller;

    /**
     * @var Mockery\MockInterface|UserService
     */
    protected $userServiceMock;

    /**
     * @var Mockery\MockInterface|AuthManager
     */
    protected $authMock;

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
        $this->userServiceMock = Mockery::mock(UserService::class);
        $this->authMock = Mockery::mock(AuthManager::class);
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
        $this->requestMock = Mockery::mock(RequestInterface::class);
        $this->responseMock = Mockery::mock(ResponseInterface::class);

        // 获取容器并注册mocks
        $container = ApplicationContext::getContainer();
        $container->set(UserService::class, $this->userServiceMock);
        $container->set(AuthManager::class, $this->authMock);
        $container->set(LoggerInterface::class, $this->loggerMock);
        $container->set(RequestInterface::class, $this->requestMock);
        $container->set(ResponseInterface::class, $this->responseMock);

        // 模拟auth guard行为
        $guardMock = Mockery::mock();
        $this->authMock->shouldReceive('guard')->with('jwt')->andReturn($guardMock);

        // 直接创建控制器并设置依赖
        $this->controller = new AuthController();
        $reflection = new \ReflectionClass($this->controller);
        
        // 设置各个属性
        $userServiceProperty = $reflection->getProperty('userService');
        $userServiceProperty->setAccessible(true);
        $userServiceProperty->setValue($this->controller, $this->userServiceMock);
        
        $authProperty = $reflection->getProperty('auth');
        $authProperty->setAccessible(true);
        $authProperty->setValue($this->controller, $this->authMock);
        
        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($this->controller, $this->loggerMock);
        
        $requestProperty = $reflection->getProperty('request');
        $requestProperty->setAccessible(true);
        $requestProperty->setValue($this->controller, $this->requestMock);
        
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
     * 测试用户注册成功的情况
     */
    public function testRegisterSuccess()
    {
        // 准备测试数据
        $params = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'username' => 'testuser'
        ];
        
        $userMock = (object)[
            'id' => 1,
            'username' => 'testuser',
            'email' => 'test@example.com',
            'status' => 1
        ];
        
        $token = 'mock-jwt-token';

        // 模拟请求参数
        $this->requestMock->shouldReceive('all')->andReturn($params);

        // 模拟服务返回
        $this->userServiceMock->shouldReceive('createUser')
            ->with($params)
            ->andReturn($userMock);

        // 模拟auth登录
        $guardMock = Mockery::mock();
        $this->authMock->shouldReceive('guard')->with('jwt')->andReturn($guardMock);
        $guardMock->shouldReceive('login')->with($userMock)->andReturn($token);

        // 模拟日志记录
        $this->loggerMock->shouldReceive('info')
            ->with('用户注册成功', ['email' => $params['email'], 'user_id' => $userMock->id]);

        // 执行测试
        $result = $this->controller->register();

        // 验证结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result);
        $this->assertEquals(0, $result['code']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('token', $result['data']);
        $this->assertEquals($token, $result['data']['token']);
    }

    /**
     * 测试用户注册失败-参数不完整
     */
    public function testRegisterWithMissingParams()
    {
        // 准备测试数据
        $params = ['email' => 'test@example.com'];

        // 模拟请求参数
        $this->requestMock->shouldReceive('all')->andReturn($params);

        // 执行测试
        $result = $this->controller->register();

        // 验证结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result);
        $this->assertEquals(400, $result['code']);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('邮箱和密码不能为空', $result['message']);
    }

    /**
     * 测试用户注册失败-邮箱格式不正确
     */
    public function testRegisterWithInvalidEmail()
    {
        // 准备测试数据
        $params = ['email' => 'invalid-email', 'password' => 'password123'];

        // 模拟请求参数
        $this->requestMock->shouldReceive('all')->andReturn($params);

        // 执行测试
        $result = $this->controller->register();

        // 验证结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result);
        $this->assertEquals(400, $result['code']);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('邮箱格式不正确', $result['message']);
    }

    /**
     * 测试用户登录成功的情况
     */
    public function testLoginSuccess()
    {
        // 准备测试数据
        $email = 'test@example.com';
        $password = 'password123';
        
        $userMock = (object)[
            'id' => 1,
            'username' => 'testuser',
            'email' => 'test@example.com',
            'real_name' => 'Test User',
            'avatar' => 'avatar.jpg',
            'role' => 'user',
            'status' => 1
        ];
        
        $token = 'mock-jwt-token';

        // 模拟请求参数
        $this->requestMock->shouldReceive('input')->with('email', '')->andReturn($email);
        $this->requestMock->shouldReceive('input')->with('password', '')->andReturn($password);

        // 模拟服务返回
        $this->userServiceMock->shouldReceive('validateCredentials')
            ->with($email, $password)
            ->andReturn($userMock);

        // 模拟auth登录
        $guardMock = Mockery::mock();
        $this->authMock->shouldReceive('guard')->with('jwt')->andReturn($guardMock);
        $guardMock->shouldReceive('login')->with($userMock)->andReturn($token);

        // 模拟日志记录
        $this->loggerMock->shouldReceive('info')
            ->with('用户登录成功', ['email' => $email, 'user_id' => $userMock->id]);

        // 执行测试
        $result = $this->controller->login();

        // 验证结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result);
        $this->assertEquals(0, $result['code']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('token', $result['data']);
        $this->assertEquals($token, $result['data']['token']);
    }

    /**
     * 测试用户登录失败-凭据错误
     */
    public function testLoginWithInvalidCredentials()
    {
        // 准备测试数据
        $email = 'test@example.com';
        $password = 'wrongpassword';

        // 模拟请求参数
        $this->requestMock->shouldReceive('input')->with('email', '')->andReturn($email);
        $this->requestMock->shouldReceive('input')->with('password', '')->andReturn($password);

        // 模拟服务返回
        $this->userServiceMock->shouldReceive('validateCredentials')
            ->with($email, $password)
            ->andReturn(false);

        // 模拟日志记录
        $this->loggerMock->shouldReceive('warning')
            ->with('用户验证失败', ['email' => $email]);

        // 执行测试
        $result = $this->controller->login();

        // 验证结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result);
        $this->assertEquals(401, $result['code']);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals('邮箱或密码错误', $result['message']);
    }

    /**
     * 测试用户登出成功的情况
     */
    public function testLogoutSuccess()
    {
        // 准备测试数据
        $userMock = (object)['id' => 1];

        // 模拟auth行为
        $guardMock = Mockery::mock();
        $guardMock->shouldReceive('user')->andReturn($userMock);
        $guardMock->shouldReceive('logout');
        $this->authMock->shouldReceive('guard')->with('jwt')->andReturn($guardMock);

        // 模拟日志记录
        $this->loggerMock->shouldReceive('info')
            ->with('用户登出成功', ['user_id' => $userMock->id]);

        // 执行测试
        $result = $this->controller->logout();

        // 验证结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result);
        $this->assertEquals(0, $result['code']);
        $this->assertEquals('登出成功', $result['message']);
    }

    /**
     * 测试获取当前用户信息成功的情况
     */
    public function testMeSuccess()
    {
        // 准备测试数据
        $userMock = (object)[
            'id' => 1,
            'username' => 'testuser',
            'email' => 'test@example.com',
            'real_name' => 'Test User',
            'avatar' => 'avatar.jpg',
            'bio' => 'Test bio',
            'role' => 'user',
            'status' => 1,
            'created_at' => '2023-01-01 00:00:00'
        ];

        // 模拟auth行为
        $guardMock = Mockery::mock();
        $guardMock->shouldReceive('user')->andReturn($userMock);
        $this->authMock->shouldReceive('guard')->with('jwt')->andReturn($guardMock);

        // 模拟日志记录
        $this->loggerMock->shouldReceive('info')
            ->with('获取用户信息成功', ['user_id' => $userMock->id]);

        // 执行测试
        $result = $this->controller->me();

        // 验证结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result);
        $this->assertEquals(0, $result['code']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('user', $result['data']);
    }

    /**
     * 测试获取当前用户信息失败-未登录
     */
    public function testMeWithNoUser()
    {
        // 模拟auth行为
        $guardMock = Mockery::mock();
        $guardMock->shouldReceive('user')->andReturn(null);
        $this->authMock->shouldReceive('guard')->with('jwt')->andReturn($guardMock);

        // 执行测试
        $result = $this->controller->me();

        // 验证结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('code', $result);
        $this->assertEquals(401, $result['code']);
        $this->assertEquals('用户未登录', $result['message']);
    }
}