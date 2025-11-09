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

namespace App\Task;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * 抽象任务类
 * 所有异步任务的基类，提供通用功能
 */
abstract class AbstractTask
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 任务数据
     *
     * @var array
     */
    protected $data;

    /**
     * 任务执行结果
     *
     * @var mixed
     */
    protected $result;

    /**
     * 任务开始时间
     *
     * @var float
     */
    protected $startTime;

    /**
     * 构造函数
     *
     * @param array $data 任务数据
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        $this->result = null;
    }

    /**
     * 执行任务
     *
     * @return mixed 任务执行结果
     */
    final public function execute()
    {
        $this->startTime = microtime(true);
        $this->logger->info('任务开始执行', [
            'task_type' => get_class($this),
            'task_data' => $this->maskSensitiveData($this->data),
        ]);

        try {
            // 执行实际任务
            $this->result = $this->handle();

            // 记录任务执行成功
            $this->logger->info('任务执行成功', [
                'task_type' => get_class($this),
                'execution_time' => $this->getExecutionTime(),
            ]);

            return $this->result;
        } catch (\Throwable $e) {
            // 记录任务执行失败
            $this->logger->error('任务执行失败', [
                'task_type' => get_class($this),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'execution_time' => $this->getExecutionTime(),
            ]);

            // 可以在这里添加重试逻辑或错误处理
            $this->handleError($e);

            throw $e;
        }
    }

    /**
     * 处理任务的具体逻辑
     * 由子类实现
     *
     * @return mixed
     */
    abstract protected function handle();

    /**
     * 错误处理
     * 子类可以覆盖此方法实现自定义错误处理
     *
     * @param \Throwable $e
     */
    protected function handleError(\Throwable $e): void
    {
        // 默认实现，不做处理
        // 子类可以覆盖此方法，例如添加重试逻辑
    }

    /**
     * 获取任务执行时间
     *
     * @return float 执行时间（毫秒）
     */
    private function getExecutionTime(): float
    {
        return round((microtime(true) - $this->startTime) * 1000, 2);
    }

    /**
     * 掩码敏感数据
     * 避免在日志中记录敏感信息
     *
     * @param array $data
     * @return array
     */
    private function maskSensitiveData(array $data): array
    {
        // 敏感字段列表
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'email', 'phone'];
        $result = $data;

        foreach ($sensitiveFields as $field) {
            if (array_key_exists($field, $result)) {
                $result[$field] = '******';
            }
        }

        return $result;
    }

    /**
     * 获取任务结果
     *
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * 获取任务数据
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}