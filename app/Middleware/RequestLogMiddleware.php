<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Context\ApplicationContext;
use Hyperf\Logger\LoggerFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * 请求日志中间件
 * 使用Hyperf框架内置的Logger组件记录API请求日志
 */
class RequestLogMiddleware implements MiddlewareInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct()
    {
        // 使用Hyperf内置的LoggerFactory获取logger实例
        $this->logger = ApplicationContext::getContainer()->get(LoggerFactory::class)->get('request');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 记录请求开始时间
        $startTime = microtime(true);
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();
        $ip = $request->getServerParams()['remote_addr'] ?? 'unknown';
        
        // 记录请求信息，但避免记录敏感数据
        $this->logger->info('Request started', [
            'path' => $path,
            'method' => $method,
            'ip' => $ip,
        ]);

        // 处理请求
        $response = $handler->handle($request);

        // 计算请求耗时
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $statusCode = $response->getStatusCode();

        // 记录响应信息
        $this->logger->info('Request completed', [
            'path' => $path,
            'method' => $method,
            'status_code' => $statusCode,
            'duration_ms' => $duration,
        ]);

        // 记录异常请求
        if ($statusCode >= 500) {
            $this->logger->error('Request failed', [
                'path' => $path,
                'method' => $method,
                'status_code' => $statusCode,
                'duration_ms' => $duration,
            ]);
        }

        return $response;
    }
}