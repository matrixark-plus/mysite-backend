\u003c?php

declare(strict_types=1);

namespace HyperfTest\Service;

use App\Service\SystemService;
use Hyperf\Context\ApplicationContext;
use Hyperf\Database\ConnectionInterface;
use Hyperf\Redis\RedisFactory;
use Hyperf\Config\ConfigInterface;
use Hyperf\Logger\LoggerInterface;
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
     * @var Mockery\MockInterface|\Redis
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

    protected function setUp(): void
    {
        parent::setUp();

        // 创建mocks
        $this-\u003edbMock = Mockery::mock(ConnectionInterface::class);
        $this-\u003eredisMock = Mockery::mock('\Redis');
        $this-\u003eredisFactoryMock = Mockery::mock(RedisFactory::class);
        $this-\u003econfigMock = Mockery::mock(ConfigInterface::class);
        $this-\u003eloggerMock = Mockery::mock(LoggerInterface::class);

        // 配置redis factory mock
        $this-\u003eredisFactoryMock-\u003eshouldReceive('get')
            -\u003ewith('default')
            -\u003eandReturn($this-\u003eredisMock);

        // 配置默认配置
        $this-\u003econfigMock-\u003eshouldReceive('get')
            -\u003eandReturn([]);

        // 获取容器并注册mocks
        $container = ApplicationContext::getContainer();
        $container-\u003eset(ConnectionInterface::class, $this-\u003edbMock);
        $container-\u003eset(RedisFactory::class, $this-\u003eredisFactoryMock);
        $container-\u003eset(ConfigInterface::class, $this-\u003econfigMock);
        $container-\u003eset(LoggerInterface::class, $this-\u003eloggerMock);

        // 创建服务实例
        $this-\u003eservice = new SystemService();
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
        // 准备测试数据
        $params = ['time_range' =\u003e 'week'];
        $cacheKey = 'system:statistics:week';
        $cachedData = [
            'user_count' =\u003e 100,
            'article_count' =\u003e 50,
            'comment_count' =\u003e 200,
            'view_count' =\u003e 1000
        ];

        // 配置redis mock行为
        $this-\u003eredisMock-\u003eshouldReceive('get')
            -\u003eonce()
            -\u003ewith($cacheKey)
            -\u003eandReturn(json_encode($cachedData));

        // 执行测试
        $result = $this-\u003eservice-\u003 egetStatistics($params);

        // 验证结果
        $this-\u003eassertEquals($cachedData, $result);
    }

    /**
     * 测试从数据库获取统计数据（缓存未命中）
     */
    public function testGetStatisticsFromDatabase()
    {
        // 准备测试数据
        $params = ['time_range' =\u003e 'month'];
        $cacheKey = 'system:statistics:month';
        $timeRange = ['start_time' =\u003e '2025-10-01 00:00:00', 'end_time' =\u003e '2025-10-31 23:59:59'];
        $expectedResult = [
            'user_count' =\u003e 200,
            'article_count' =\u003e 80,
            'comment_count' =\u003e 400,
            'view_count' =\u003e 5000
        ];

        // 配置mock行为
        $this-\u003eredisMock-\u003eshouldReceive('get')
            -\u003eonce()
            -\u003ewith($cacheKey)
            -\u003eandReturn(false);

        // 模拟各个统计方法的返回值
        $this-\u003eservice = Mockery::mock(SystemService::class . '[getUserCount, getArticleCount, getCommentCount, getViewCount, getTimeRange]', [])
            -\u003emakePartial();
        $this-\u003eservice-\u003eshouldReceive('getTimeRange')
            -\u003eonce()
            -\u003ewith($params)
            -\u003eandReturn($timeRange);
        $this-\u003eservice-\u003eshouldReceive('getUserCount')
            -\u003eonce()
            -\u003ewith($timeRange)
            -\u003eandReturn(200);
        $this-\u003eservice-\u003eshouldReceive('getArticleCount')
            -\u003eonce()
            -\u003ewith($timeRange)
            -\u003eandReturn(80);
        $this-\u003eservice-\u003eshouldReceive('getCommentCount')
            -\u003eonce()
            -\u003ewith($timeRange)
            -\u003eandReturn(400);
        $this-\u003eservice-\u003eshouldReceive('getViewCount')
            -\u003eonce()
            -\u003ewith($timeRange)
            -\u003eandReturn(5000);

        // 配置redis存储行为
        $this-\u003eredisMock-\u003eshouldReceive('setex')
            -\u003eonce()
            -\u003ewith($cacheKey, 3600, json_encode($expectedResult));

        // 执行测试
        $result = $this-\u003eservice-\u003 egetStatistics($params);

        // 验证结果
        $this-\u003eassertEquals($expectedResult, $result);
    }

    /**
     * 测试获取用户数量
     */
    public function testGetUserCount()
    {
        // 准备测试数据
        $timeRange = ['start_time' =\u003e '2025-10-01 00:00:00', 'end_time' =\u003e '2025-10-31 23:59:59'];
        $expectedCount = 150;

        // 配置db mock行为
        $builderMock = Mockery::mock();
        $builderMock-\u003eshouldReceive('whereBetween')
            -\u003eonce()
            -\u003ewith('created_at', [$timeRange['start_time'], $timeRange['end_time']])
            -\u003eandReturnSelf();
        $builderMock-\u003eshouldReceive('count')
            -\u003eonce()
            -\u003eandReturn($expectedCount);

        $this-\u003edbMock-\u003eshouldReceive('table')
            -\u003eonce()
            -\u003ewith('users')
            -\u003eandReturn($builderMock);

        // 执行测试
        $result = $this-\u003eservice-\u003 egetUserCount($timeRange);

        // 验证结果
        $this-\u003eassertEquals($expectedCount, $result);
    }

    /**
     * 测试获取文章数量
     */
    public function testGetArticleCount()
    {
        // 准备测试数据
        $timeRange = ['start_time' =\u003e '2025-10-01 00:00:00', 'end_time' =\u003e '2025-10-31 23:59:59'];
        $expectedCount = 60;

        // 配置db mock行为
        $builderMock = Mockery::mock();
        $builderMock-\u003eshouldReceive('whereBetween')
            -\u003eonce()
            -\u003ewith('created_at', [$timeRange['start_time'], $timeRange['end_time']])
            -\u003eandReturnSelf();
        $builderMock-\u003eshouldReceive('count')
            -\u003eonce()
            -\u003eandReturn($expectedCount);

        $this-\u003edbMock-\u003eshouldReceive('table')
            -\u003eonce()
            -\u003ewith('blogs')
            -\u003eandReturn($builderMock);

        // 执行测试
        $result = $this-\u003eservice-\u003 egetArticleCount($timeRange);

        // 验证结果
        $this-\u003eassertEquals($expectedCount, $result);
    }

    /**
     * 测试获取评论数量
     */
    public function testGetCommentCount()
    {
        // 准备测试数据
        $timeRange = ['start_time' =\u003e '2025-10-01 00:00:00', 'end_time' =\u003e '2025-10-31 23:59:59'];
        $expectedCount = 300;

        // 配置db mock行为
        $builderMock = Mockery::mock();
        $builderMock-\u003eshouldReceive('whereBetween')
            -\u003eonce()
            -\u003ewith('created_at', [$timeRange['start_time'], $timeRange['end_time']])
            -\u003eandReturnSelf();
        $builderMock-\u003eshouldReceive('count')
            -\u003eonce()
            -\u003eandReturn($expectedCount);

        $this-\u003edbMock-\u003eshouldReceive('table')
            -\u003eonce()
            -\u003ewith('comments')
            -\u003eandReturn($builderMock);

        // 执行测试
        $result = $this-\u003eservice-\u003 egetCommentCount($timeRange);

        // 验证结果
        $this-\u003eassertEquals($expectedCount, $result);
    }

    /**
     * 测试获取时间范围
     */
    public function testGetTimeRange()
    {
        // 测试不同时间范围参数
        $tests = [
            ['params' =\u003e ['time_range' =\u003e 'today'], 'days' =\u003e 1],
            ['params' =\u003e ['time_range' =\u003e 'week'], 'days' =\u003e 7],
            ['params' =\u003e ['time_range' =\u003e 'month'], 'days' =\u003e 30],
            ['params' =\u003e [], 'days' =\u003e 30] // 默认情况
        ];

        foreach ($tests as $test) {
            $result = $this-\u003eservice-\u003 egetTimeRange($test['params']);
            
            $this-\u003eassertArrayHasKey('start_time', $result);
            $this-\u003eassertArrayHasKey('end_time', $result);
            
            // 验证时间范围是否正确
            $startTime = new \DateTime($result['start_time']);
            $endTime = new \DateTime($result['end_time']);
            $interval = $startTime-\u003ediff($endTime);
            
            // 考虑到时间范围可能包含当前时刻，这里只做大致验证
            $this-\u003eassertLessThanOrEqual($test['days'] + 1, $interval-\u003edays);
        }
    }
}