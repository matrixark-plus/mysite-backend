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

use Exception;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;
use Hyperf\Context\ApplicationContext;

/**
 * 服务基类
 * 提供所有Service共享的通用功能和方法
 * 用于优化服务层代码，减少冗余
 */
class BaseService
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 生成成功响应
     *
     * @param array|null $data 响应数据
     * @param string $message 响应消息
     * @return array 统一格式的成功响应
     */
    protected function success(array $data = null, string $message = 'success'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * 生成失败响应
     *
     * @param string $message 错误消息
     * @param array|null $data 额外数据（可选）
     * @return array 统一格式的失败响应
     */
    protected function fail(string $message, array $data = null): array
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * 验证必填字段
     *
     * @param array $data 待验证的数据
     * @param array $requiredFields 必填字段列表
     * @return string|null 错误信息，如果验证通过则返回null
     */
    protected function validateRequiredFields(array $data, array $requiredFields): ?string
    {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return "缺少必要字段: {$field}";
            }
        }
        return null;
    }

    /**
     * 执行带异常处理的操作
     * 用于封装try-catch逻辑，减少重复代码
     *
     * @param callable $callback 要执行的操作
     * @param string $errorMessage 自定义错误消息
     * @param array $logData 记录到日志的数据
     * @return array 操作结果
     */
    protected function executeWithErrorHandling(callable $callback, string $errorMessage = '操作失败', array $logData = []): array
    {
        try {
            $result = $callback();
            return $this->success($result);
        } catch (Exception $e) {
            $this->logger->error($errorMessage . ': ' . $e->getMessage(), $logData);
            return $this->fail($errorMessage);
        }
    }

    /**
     * 获取服务实例
     * 用于在需要时动态获取其他服务
     *
     * @param string $serviceClass 服务类名
     * @return object 服务实例
     */
    protected function getService(string $serviceClass)
    {
        $container = ApplicationContext::getContainer();
        return $container->get($serviceClass);
    }

    /**
     * 记录操作日志
     *
     * @param string $action 操作名称
     * @param array $data 操作数据
     * @param string $level 日志级别
     */
    protected function logAction(string $action, array $data = [], string $level = 'info'): void
    {
        $logMethod = $level;
        if (!method_exists($this->logger, $logMethod)) {
            $logMethod = 'info';
        }
        
        $this->logger->{$logMethod}("[操作] {$action}", $data);
    }
}
