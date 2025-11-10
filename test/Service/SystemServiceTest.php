<?php

declare(strict_types=1);

namespace Test\Service;

use App\Service\SystemService;
use Hyperf\Context\ApplicationContext;
use Hyperf\Database\ConnectionInterface;
use Hyperf\Redis\RedisFactory;
use Hyperf\Config\ConfigInterface;
use Psr\Log\LoggerInterface;
use Hyperf\Testing\TestCase;
use Mockery;

/**
 * SystemService的单元测试
 * 测试系统服务的各项功能
 */
class SystemServiceTest extends TestCase
{
    /**
 * @var SystemService
 */
protected $service;

/**
 * @var Mockery\MockInterface|ConnectionInterface
 */
protected $dbMock;

/**
 * @var Mockery\MockInterface|\Hyperf\Redis\RedisProxy
 */
protected $redisMock;

/**
 * @var Mockery\MockInterface|RedisFactory
 */
protected $redisFactoryMock;

/**
 * @var Mockery\MockInterface|ConfigInterface
 */
protected $configMock;

/**
 * @var Mockery\MockInterface|LoggerInterface
 */
protected $loggerMock;

/**
 * @var Mockery\MockInterface
 */
protected $userRepositoryMock;

/**
 * @var Mockery\MockInterface
 */
protected $blogRepositoryMock;

/**
 * @var Mockery\MockInterface
 */
protected $commentRepositoryMock;

/**
 * @var Mockery\MockInterface
 */
protected $activityLogRepositoryMock;

/**
 * @var Mockery\MockInterface
 */
protected $environmentFileServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建mocks
        $this->dbMock = Mockery::mock(ConnectionInterface::class);
        $this->redisMock = Mockery::mock('\Hyperf\Redis\RedisProxy');
        $this->redisFactoryMock = Mockery::mock(RedisFactory::class);
        $this->configMock = Mockery::mock(ConfigInterface::class);
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
        $this->activityLogRepositoryMock = Mockery::mock('\App\Repository\ActivityLogRepository');
        $this->environmentFileServiceMock = Mockery::mock('\App\Service\EnvironmentFileService');
        
        // Mock模型类
        $this->mockBlogModel();

        // 配置redis factory mock
        $this->redisFactoryMock->shouldReceive('get')
            ->with('default')
            ->andReturn($this->redisMock);

        // 配置默认配置
        $this->configMock->shouldReceive('get')
            ->andReturn([]);
            
        // 配置logger mock行为
        $this->loggerMock->shouldReceive('error')
            ->andReturnNull();
            
        // 移除DB模拟，因为我们现在直接模拟Repository方法

            
        // 配置活动日志repository mock行为
        $this->activityLogRepositoryMock->shouldReceive('getRecentActivities')->andReturn([]);
        
        // 实例化真实的Repository类
        $this->userRepositoryMock = new \App\Repository\UserRepository();
        $this->blogRepositoryMock = new \App\Repository\BlogRepository();
        $this->commentRepositoryMock = new \App\Repository\CommentRepository();
        
        // 使用反射为Repository类注入logger
        $repositories = [
            $this->userRepositoryMock,
            $this->blogRepositoryMock,
            $this->commentRepositoryMock
        ];
        
        foreach ($repositories as $repository) {
            $reflection = new \ReflectionClass($repository);
            try {
                $property = $reflection->getProperty('logger');
                $property->setAccessible(true);
                $property->setValue($repository, $this->loggerMock);
            } catch (\ReflectionException $e) {
                // 属性不存在时忽略
            }
        }

        // 获取容器并注册mocks
        $container = ApplicationContext::getContainer();
        $container->set(ConnectionInterface::class, $this->dbMock);
        $container->set(RedisFactory::class, $this->redisFactoryMock);
        $container->set(ConfigInterface::class, $this->configMock);
        $container->set(LoggerInterface::class, $this->loggerMock);
        $container->set('\App\Repository\UserRepository', $this->userRepositoryMock);
        $container->set('\App\Repository\BlogRepository', $this->blogRepositoryMock);
        $container->set('\App\Repository\CommentRepository', $this->commentRepositoryMock);
        $container->set('\App\Repository\ActivityLogRepository', $this->activityLogRepositoryMock);
        $container->set('\App\Service\EnvironmentFileService', $this->environmentFileServiceMock);

        // 创建服务实例，传入所需的依赖
        $this->service = new SystemService($this->redisFactoryMock, $this->configMock, $this->loggerMock);
        
        // 手动注入@Inject的依赖
        $reflection = new \ReflectionClass($this->service);
        $this->setInjectedProperty($reflection, $this->service, 'userRepository', $this->userRepositoryMock);
        $this->setInjectedProperty($reflection, $this->service, 'blogRepository', $this->blogRepositoryMock);
        $this->setInjectedProperty($reflection, $this->service, 'commentRepository', $this->commentRepositoryMock);
        $this->setInjectedProperty($reflection, $this->service, 'activityLogRepository', $this->activityLogRepositoryMock);
        $this->setInjectedProperty($reflection, $this->service, 'environmentFileService', $this->environmentFileServiceMock);
    }
    
    /**
     * 帮助方法：手动设置@Inject注入的属性
     */
    private function setInjectedProperty(\ReflectionClass $reflection, object $instance, string $propertyName, $value): void
    {
        try {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $property->setValue($instance, $value);
        } catch (\ReflectionException $e) {
            // 属性不存在时忽略
        }
    }

    /**
     * Mock Blog模型的静态方法
     */
    private function mockBlogModel()
    {
        // 创建查询构建器mock
        $builderMock = Mockery::mock('\Hyperf\Database\Query\Builder');
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereBetween')
            ->with('created_at', Mockery::any())
            ->andReturnSelf();
        $builderMock->shouldReceive('count')->andReturn(50);
        
        // Mock Blog模型的静态方法
        $blogModelMock = Mockery::mock('overload:\App\Model\Blog');
        $blogModelMock->shouldReceive('query')->andReturn($builderMock);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试从缓存获取统计数据
     */
    public function testGetStatisticsFromCache()
    {
        // 设置Redis返回缓存数据
        $cachedData = [
            'user_count' => 100,
            'article_count' => 50,
            'comment_count' => 200,
            'view_count' => 1000
        ];
        
        // Mock Redis的get方法，匹配各种键
        $this->redisMock->shouldReceive('get')
            ->with(Mockery::pattern('/^(system:statistics|statistics:view_count)/'))
            ->andReturnUsing(function($key) use ($cachedData) {
                // 如果是view_count键，返回特定值
                if ($key === 'statistics:view_count') {
                    return '1000';
                }
                // 否则返回完整的统计数据
                return json_encode($cachedData);
            });
        
        // 直接实例化SystemService并注入依赖
        $this->service = new SystemService($this->redisFactoryMock, $this->configMock, $this->loggerMock);
        
        // 为服务手动注入@Inject的依赖
        $reflection = new \ReflectionClass($this->service);
        $this->setInjectedProperty($reflection, $this->service, 'userRepository', $this->userRepositoryMock);
        $this->setInjectedProperty($reflection, $this->service, 'blogRepository', $this->blogRepositoryMock);
        $this->setInjectedProperty($reflection, $this->service, 'commentRepository', $this->commentRepositoryMock);
        $this->setInjectedProperty($reflection, $this->service, 'activityLogRepository', $this->activityLogRepositoryMock);
        
        // 调用方法并验证结果
        $result = $this->service->getStatistics(['time_range' => 'week']);
        
        // 验证结果
        $this->assertEquals($cachedData, $result);
    }

    /**
 * 测试从数据库获取统计数据（缓存未命中）
 */
    public function testGetStatisticsFromDatabase()
    {
        // 设置Redis返回null（缓存未命中）
        $this->redisMock->shouldReceive('get')
            ->with(Mockery::pattern('/^system:statistics/'))
            ->andReturn(false);
        
        // 设置Redis返回view_count
        $this->redisMock->shouldReceive('get')
            ->with('statistics:view_count')
            ->andReturn('1000');
        
        // 完全放松Redis set方法的验证，允许任何参数组合
        $this->redisMock->shouldReceive('set')
            ->andReturn(true);
        
        // 直接实例化SystemService并注入依赖
        $this->service = new SystemService($this->redisFactoryMock, $this->configMock, $this->loggerMock);
        
        // 为服务手动注入@Inject的依赖
        $reflection = new \ReflectionClass($this->service);
        $this->setInjectedProperty($reflection, $this->service, 'userRepository', $this->userRepositoryMock);
        $this->setInjectedProperty($reflection, $this->service, 'blogRepository', $this->blogRepositoryMock);
        $this->setInjectedProperty($reflection, $this->service, 'commentRepository', $this->commentRepositoryMock);
        $this->setInjectedProperty($reflection, $this->service, 'activityLogRepository', $this->activityLogRepositoryMock);
        
        // 调用方法并验证结果
        $result = $this->service->getStatistics([]);
        
        // 验证结果
        $this->assertIsArray($result);
        $this->assertEquals(100, $result['user_count']);
        $this->assertEquals(50, $result['article_count']);
        $this->assertEquals(200, $result['comment_count']);
        $this->assertArrayHasKey('view_count', $result);
    }
    
    /**
     * 测试获取用户数量
     */
    public function testGetUserCount()
    {
        // 准备测试数据
        $timeRange = ['start_time' => '2025-10-01 00:00:00', 'end_time' => '2025-10-31 23:59:59'];
        
        // 直接模拟UserRepository的方法返回预期值
        $this->userRepositoryMock->shouldReceive('countUser')
            ->andReturn(100);

        // 使用反射访问protected方法
        $reflectionMethod = new \ReflectionMethod(SystemService::class, 'getUserCount');
        $reflectionMethod->setAccessible(true);

        // 执行测试
        $result = $reflectionMethod->invoke($this->service, $timeRange);

        // 验证结果
        $this->assertEquals(100, $result); // 与mock返回值匹配
    }

    /**
     * 测试获取文章数量
     */
    public function testGetArticleCount()
    {
        // 准备测试数据
        $timeRange = ['start_time' => '2025-10-01 00:00:00', 'end_time' => '2025-10-31 23:59:59'];
        
        // 直接模拟BlogRepository的方法返回预期值
        $this->blogRepositoryMock->shouldReceive('countBlog')
            ->andReturn(50);

        // 使用反射访问protected方法
        $reflectionMethod = new \ReflectionMethod(SystemService::class, 'getArticleCount');
        $reflectionMethod->setAccessible(true);

        // 执行测试
        $result = $reflectionMethod->invoke($this->service, $timeRange);

        // 验证结果
        $this->assertEquals(50, $result); // 与mock返回值匹配
    }

    /**
     * 测试获取评论数量
     */
    public function testGetCommentCount()
    {
        // 准备测试数据
        $timeRange = ['start_time' => '2025-10-01 00:00:00', 'end_time' => '2025-10-31 23:59:59'];
        
        // 直接模拟CommentRepository的方法返回预期值
        $this->commentRepositoryMock->shouldReceive('countComment')
            ->andReturn(200);

        // 使用反射访问protected方法
        $reflectionMethod = new \ReflectionMethod(SystemService::class, 'getCommentCount');
        $reflectionMethod->setAccessible(true);

        // 执行测试
        $result = $reflectionMethod->invoke($this->service, $timeRange);

        // 验证结果
        $this->assertEquals(200, $result); // 与mock返回值匹配
    }

    /**
     * 测试获取时间范围
     */
    public function testGetTimeRange()
    {
        // 使用反射访问protected方法
        $reflectionMethod = new \ReflectionMethod(SystemService::class, 'getTimeRange');
        $reflectionMethod->setAccessible(true);

        // 执行测试 - 传入空参数数组，应该返回默认时间范围
        $result = $reflectionMethod->invoke($this->service, []);

        // 验证结果 - 检查返回数组包含正确的键
        $this->assertIsArray($result);
        $this->assertArrayHasKey('start', $result);
        $this->assertArrayHasKey('end', $result);
    }
}