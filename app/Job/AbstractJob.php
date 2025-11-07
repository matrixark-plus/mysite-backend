<?php

declare(strict_types=1);

namespace App\Job;

use Hyperf\Context\ApplicationContext;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * 任务基类
 * 所有任务都应该继承此类
 */
abstract class AbstractJob
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array 任务参数
     */
    protected $params;

    /**
     * @var int 重试次数
     */
    protected $attempts = 0;

    /**
     * @var int 最大重试次数
     */
    protected $maxAttempts = 3;

    public function __construct(array $params = [])
    {
        $this->params = $params;
        $container = ApplicationContext::getContainer();
        $this->logger = $container->get(LoggerFactory::class)->get('job');
    }

    /**
     * 执行任务
     */
    abstract public function handle();

    /**
     * 执行任务并处理异常
     */
    public function execute(): bool
    {
        $this->attempts++;
        try {
            $this->handle();
            return true;
        } catch (\Exception $e) {
            $this->failed($e->getMessage());
            
            // 如果失败次数小于最大重试次数，重新执行
            if ($this->attempts < $this->maxAttempts) {
                $this->logger->info('Retrying job', ['job' => get_class($this), 'attempt' => $this->attempts]);
                return $this->execute();
            }
            
            return false;
        }
    }

    /**
     * 任务失败处理
     * @param string $throwable
     */
    public function failed(string $throwable): void
    {
        $this->logger->error('Job failed', [
            'job' => get_class($this),
            'params' => $this->params,
            'error' => $throwable,
            'attempts' => $this->attempts,
        ]);
    }

    /**
     * 获取任务参数
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * 获取当前重试次数
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * 获取最大重试次数
     */
    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }
}