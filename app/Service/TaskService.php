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

namespace App\Service;

use App\Task\AbstractTask;
use App\Task\AsyncLogTask;
use Hyperf\Context\ApplicationContext;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * 任务服务
 * 管理和分发异步任务，确保任务能够被正确执行
 */
class TaskService
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var LoggerFactory
     */
    protected $loggerFactory;

    /**
     * 异步执行任务
     *
     * @param string $taskClass 任务类名
     * @param array $data 任务数据
     * @param bool $wait 是否等待任务完成
     * @return bool|mixed 任务执行结果或是否成功提交任务
     */
    public function dispatchTask(string $taskClass, array $data, bool $wait = false)
    {
        // 验证任务类
        if (! class_exists($taskClass) || ! is_subclass_of($taskClass, AbstractTask::class)) {
            $this->logger->error('无效的任务类', ['class' => $taskClass]);
            return false;
        }

        $this->logger->info('开始分发异步任务', [
            'task_class' => $taskClass,
            'wait' => $wait,
        ]);

        try {
            if ($wait) {
                // 同步执行任务
                return $this->executeTaskSync($taskClass, $data);
            } else {
                // 异步执行任务
                return $this->executeTaskAsync($taskClass, $data);
            }
        } catch (\Throwable $e) {
            $this->logger->error('分发任务失败', [
                'task_class' => $taskClass,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * 同步执行任务
     *
     * @param string $taskClass 任务类名
     * @param array $data 任务数据
     * @return mixed 任务执行结果
     */
    protected function executeTaskSync(string $taskClass, array $data)
    {
        // 创建任务实例
        /** @var AbstractTask $task */
        $task = new $taskClass($data);

        // 执行任务
        return $task->execute();
    }

    /**
     * 异步执行任务
     * 使用Hyperf的协程实现真正的异步处理
     *
     * @param string $taskClass 任务类名
     * @param array $data 任务数据
     * @return bool 是否成功提交任务
     */
    protected function executeTaskAsync(string $taskClass, array $data): bool
    {
        // 创建新协程执行任务
        Coroutine::create(function () use ($taskClass, $data) {
            try {
                // 重新创建容器实例，避免上下文问题
                $container = ApplicationContext::getContainer();
                
                // 创建任务实例
                /** @var AbstractTask $task */
                $task = new $taskClass($data);
                
                // 手动注入logger，确保使用正确的实例
                $logger = $container->get(LoggerInterface::class);
                $task->{'logger'} = $logger;
                
                // 执行任务
                $task->execute();
            } catch (\Throwable $e) {
                // 由于是在独立协程中执行，需要确保异常被捕获
                $container = ApplicationContext::getContainer();
                $errorLogger = $container->get(LoggerFactory::class)->get('error');
                $errorLogger->error('异步任务执行失败', [
                    'task_class' => $taskClass,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        });

        return true;
    }

    /**
     * 异步写入日志
     *
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @param string $channel 日志通道
     * @return bool 是否成功提交日志任务
     */
    public function logAsync(string $level, string $message, array $context = [], string $channel = 'default'): bool
    {
        // 构建任务数据
        $taskData = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'channel' => $channel,
            'retry_count' => 0,
            'max_retries' => 3,
            'timestamp' => time(),
        ];

        // 分发异步日志任务
        return $this->dispatchTask(AsyncLogTask::class, $taskData, false);
    }

    /**
     * 批量执行异步任务
     *
     * @param array $tasks 任务列表，格式: [["task_class" => "TaskClass", "data" => []], ...]
     * @param int $concurrency 并发数
     * @return array 任务结果列表
     */
    public function dispatchBatchTasks(array $tasks, int $concurrency = 5): array
    {
        if (empty($tasks)) {
            return [];
        }

        $this->logger->info('开始批量分发异步任务', [
            'total_tasks' => count($tasks),
            'concurrency' => $concurrency,
        ]);

        // 使用协程并发执行任务
        $results = [];
        $waitGroup = new \Hyperf\Coroutine\WaitGroup();
        $channel = new \Hyperf\Coroutine\Channel($concurrency);

        try {
            foreach ($tasks as $index => $taskInfo) {
                // 确保任务信息有效
                if (!isset($taskInfo['task_class']) || !isset($taskInfo['data'])) {
                    $this->logger->warning('无效的任务信息', ['task_index' => $index]);
                    $results[$index] = false;
                    continue;
                }

                // 等待可用通道
                $channel->push($index);
                
                // 增加等待组计数
                $waitGroup->add();

                // 创建协程执行任务
                Coroutine::create(function () use ($index, $taskInfo, $waitGroup, $channel, &$results) {
                    try {
                        // 分发任务
                        $results[$index] = $this->dispatchTask(
                            $taskInfo['task_class'],
                            $taskInfo['data'],
                            true // 等待任务完成
                        );
                    } catch (\Throwable $e) {
                        $this->logger->error('批量任务执行失败', [
                            'task_index' => $index,
                            'task_class' => $taskInfo['task_class'],
                            'error' => $e->getMessage(),
                        ]);
                        $results[$index] = false;
                    } finally {
                        // 减少等待组计数
                        $waitGroup->done();
                        // 释放通道
                        $channel->pop();
                    }
                });
            }

            // 等待所有任务完成
            $waitGroup->wait();

            $this->logger->info('批量任务执行完成', [
                'total_tasks' => count($tasks),
                'success_tasks' => count(array_filter($results)),
            ]);

            return $results;
        } finally {
            // 关闭通道
            $channel->close();
        }
    }
}