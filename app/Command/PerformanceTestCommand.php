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

namespace App\Command;

use Hyperf\Utils\Coroutine;
use Hyperf\Utils\WaitGroup;
use App\Service\EventDemoService;
use App\Service\RedisLockService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Redis\RedisFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * 性能测试命令
 * 用于测试系统在高并发场景下的性能表现
 */
#[Command]
class PerformanceTestCommand extends HyperfCommand
{
    /**
     * @Inject
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @Inject
     * @var EventDemoService
     */
    protected $eventDemoService;

    /**
     * @Inject
     * @var RedisLockService
     */
    protected $redisLockService;

    /**
     * @Inject
     * @var RedisFactory
     */
    protected $redisFactory;

    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        ?string $name = 'test:performance'
    ) {
        $this->name = $name;
        parent::__construct($name);
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('系统性能测试命令，用于验证高并发性能');
        $this->addOption('type', 't', 
            \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
            '测试类型: lock(锁测试), event(事件测试), async(异步任务测试), batch(批量测试)',
            'lock'
        );
        $this->addOption('concurrency', 'c',
            \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
            '并发数',
            100
        );
        $this->addOption('iterations', 'i',
            \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
            '每个协程的迭代次数',
            10
        );
    }

    public function handle()
    {
        $type = $this->input->getOption('type');
        $concurrency = (int) $this->input->getOption('concurrency');
        $iterations = (int) $this->input->getOption('iterations');

        $this->output->writeln("开始性能测试");
        $this->output->writeln("测试类型: {$type}");
        $this->output->writeln("并发数: {$concurrency}");
        $this->output->writeln("每协程迭代: {$iterations}");
        $this->output->writeln("总请求数: " . ($concurrency * $iterations));
        $this->output->writeln(str_repeat('=', 50));

        $startTime = microtime(true);
        $results = [];

        switch ($type) {
            case 'lock':
                $results = $this->testRedisLock($concurrency, $iterations);
                break;
            case 'event':
                $results = $this->testEventDispatcher($concurrency, $iterations);
                break;
            case 'async':
                $results = $this->testAsyncTasks($concurrency, $iterations);
                break;
            case 'batch':
                $results = $this->testBatchProcessing($concurrency, $iterations);
                break;
            default:
                $this->output->error("未知的测试类型: {$type}");
                return;
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $totalRequests = $concurrency * $iterations;
        $rps = $totalRequests / $totalTime;

        $successCount = count(array_filter($results));
        $errorCount = count($results) - $successCount;

        $this->output->writeln(str_repeat('=', 50));
        $this->output->writeln("测试完成");
        $this->output->writeln(sprintf("总耗时: %.4f 秒", $totalTime));
        $this->output->writeln(sprintf("成功请求: %d", $successCount));
        $this->output->writeln(sprintf("失败请求: %d", $errorCount));
        $this->output->writeln(sprintf("每秒请求数(RPS): %.2f", $rps));
        $this->output->writeln(sprintf("平均响应时间: %.4f 毫秒", ($totalTime / $totalRequests) * 1000));

        // 记录测试报告
        $this->logger->info('性能测试报告', [
            'type' => $type,
            'concurrency' => $concurrency,
            'iterations' => $iterations,
            'total_requests' => $totalRequests,
            'total_time' => $totalTime,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'rps' => $rps,
            'avg_response_time_ms' => ($totalTime / $totalRequests) * 1000,
        ]);
    }

    /**
     * 测试Redis分布式锁性能
     */
    private function testRedisLock(int $concurrency, int $iterations): array
    {
        $this->output->writeln("开始Redis分布式锁测试...");
        
        // 清理可能存在的锁
        $redis = $this->redisFactory->get('default');
        $keys = $redis->keys('test:lock:*');
        if (! empty($keys)) {
            $redis->del($keys);
        }

        $results = [];
        $bar = $this->output->createProgressBar($concurrency);
        $bar->start();

        $waitGroup = new WaitGroup();
        $waitGroup->add($concurrency);

        for ($i = 0; $i < $concurrency; ++$i) {
            Coroutine::create(function () use ($i, $iterations, &$results, $waitGroup, $bar) {
                try {
                    $coroutineResults = [];
                    for ($j = 0; $j < $iterations; ++$j) {
                        $lockKey = 'test:lock:concurrent_test';
                        $lockValue = uniqid("{$i}_{$j}_");
                        
                        try {
                                $start = microtime(true);
                                $lockValue = $this->redisLockService->lock($lockKey, 2, 1);
                                $lockTime = microtime(true) - $start;
                                
                                if ($lockValue) {
                                    // 模拟临界区操作
                                    usleep(1000); // 1ms
                                    
                                    // 释放锁
                                    $unlocked = $this->redisLockService->unlock($lockKey, $lockValue);
                                    $coroutineResults[] = $unlocked;
                                } else {
                                    $coroutineResults[] = false;
                                }
                            
                            if ($j % 10 === 0) {
                                $this->logger->debug('锁测试进度', [
                                    'coroutine' => $i,
                                    'iteration' => $j,
                                    'locked' => $lockValue,
                                    'lock_time_ms' => $lockTime * 1000,
                                ]);
                            }
                        } catch (\Throwable $e) {
                            $this->logger->error('锁测试错误', [
                                'error' => $e->getMessage(),
                            ]);
                            $coroutineResults[] = false;
                        }
                    }
                    
                    $results = array_merge($results, $coroutineResults);
                } finally {
                    $waitGroup->done();
                    $bar->advance();
                }
            });
        }

        $waitGroup->wait();
        $bar->finish();
        $this->output->writeln('');
        
        return $results;
    }

    /**
     * 测试事件分发性能
     */
    private function testEventDispatcher(int $concurrency, int $iterations): array
    {
        $this->output->writeln("开始事件分发测试...");
        
        $results = [];
        $bar = $this->output->createProgressBar($concurrency);
        $bar->start();

        $waitGroup = new WaitGroup();
        $waitGroup->add($concurrency);

        for ($i = 0; $i < $concurrency; ++$i) {
            Coroutine::create(function () use ($i, $iterations, &$results, $waitGroup, $bar) {
                try {
                    $coroutineResults = [];
                    for ($j = 0; $j < $iterations; ++$j) {
                        try {
                            $entityType = 'test_entity';
                            $entityData = [
                                'name' => "Test Entity {$i}_{$j}",
                                'value' => $i * $j,
                                'timestamp' => time(),
                            ];
                            
                            $result = $this->eventDemoService->createEntity($entityType, $entityData);
                            $coroutineResults[] = $result['success'] ?? false;
                        } catch (\Throwable $e) {
                            $this->logger->error('事件测试错误', [
                                'error' => $e->getMessage(),
                            ]);
                            $coroutineResults[] = false;
                        }
                    }
                    
                    $results = array_merge($results, $coroutineResults);
                } finally {
                    $waitGroup->done();
                    $bar->advance();
                }
            });
        }

        $waitGroup->wait();
        $bar->finish();
        $this->output->writeln('');
        
        return $results;
    }

    /**
     * 测试异步任务性能
     */
    private function testAsyncTasks(int $concurrency, int $iterations): array
    {
        $this->output->writeln("开始异步任务测试...");
        
        $results = [];
        $bar = $this->output->createProgressBar($concurrency);
        $bar->start();

        $waitGroup = new WaitGroup();
        $waitGroup->add($concurrency);

        for ($i = 0; $i < $concurrency; ++$i) {
            Coroutine::create(function () use ($i, $iterations, &$results, $waitGroup, $bar) {
                try {
                    $coroutineResults = [];
                    for ($j = 0; $j < $iterations; ++$j) {
                        try {
                            $taskService = $this->container->get(\App\Service\TaskService::class);
                            $result = $taskService->logAsync(
                                'info',
                                '异步任务性能测试',
                                ['coroutine' => $i, 'iteration' => $j],
                                'performance'
                            );
                            $coroutineResults[] = $result;
                        } catch (\Throwable $e) {
                            $this->logger->error('异步任务测试错误', [
                                'error' => $e->getMessage(),
                            ]);
                            $coroutineResults[] = false;
                        }
                    }
                    
                    $results = array_merge($results, $coroutineResults);
                } finally {
                    $waitGroup->done();
                    $bar->advance();
                }
            });
        }

        $waitGroup->wait();
        $bar->finish();
        $this->output->writeln('');
        
        return $results;
    }

    /**
     * 测试批量处理性能
     */
    private function testBatchProcessing(int $concurrency, int $iterations): array
    {
        $this->output->writeln("开始批量处理测试...");
        
        $results = [];
        $bar = $this->output->createProgressBar($concurrency);
        $bar->start();

        $waitGroup = new WaitGroup();
        $waitGroup->add($concurrency);

        for ($i = 0; $i < $concurrency; ++$i) {
            Coroutine::create(function () use ($i, $iterations, &$results, $waitGroup, $bar) {
                try {
                    $coroutineResults = [];
                    for ($j = 0; $j < $iterations; ++$j) {
                        try {
                            // 每个批次包含5个项目
                            $batchItems = [];
                            for ($k = 0; $k < 5; ++$k) {
                                $batchItems[] = [
                                    'id' => "{$i}_{$j}_{$k}",
                                    'data' => "Batch test data {$i}_{$j}_{$k}",
                                    'timestamp' => time(),
                                ];
                            }
                            
                            $result = $this->eventDemoService->processBatchItems($batchItems);
                            $coroutineResults[] = $result['success'] >= 0;
                        } catch (\Throwable $e) {
                            $this->logger->error('批量处理测试错误', [
                                'error' => $e->getMessage(),
                            ]);
                            $coroutineResults[] = false;
                        }
                    }
                    
                    $results = array_merge($results, $coroutineResults);
                } finally {
                    $waitGroup->done();
                    $bar->advance();
                }
            });
        }

        $waitGroup->wait();
        $bar->finish();
        $this->output->writeln('');
        
        return $results;
    }
}