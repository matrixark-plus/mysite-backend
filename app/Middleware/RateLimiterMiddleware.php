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

namespace App\Middleware;

use App\Constants\StatusCode;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Redis\RedisFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Redis;
use Throwable;

/**
 * API访问频率限制中间件
 * 使用Redis实现基于IP和路径的访问频率限制，防止API滥用.
 */
class RateLimiterMiddleware implements MiddlewareInterface
{
    /**
     * @Inject
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var RedisFactory
     */
    protected $redisFactory;

    /**
     * Redis实例.
     * @var Redis
     */
    protected $redis;

    /**
     * 默认配置.
     * @var array
     */
    protected $defaultConfig = [
        'max_attempts' => 60,      // 允许的最大访问次数
        'decay_minutes' => 1,      // 时间窗口（分钟）
        'block_minutes' => 5,      // 超过限制后的封禁时间（分钟）
    ];

    /**
     * 路径特定配置
     * 可以为不同API路径设置不同的限制规则.
     * @var array
     */
    protected $pathSpecificConfig = [
        '/api/auth/login' => [
            'max_attempts' => 10,     // 登录接口限制更严格
            'decay_minutes' => 1,
            'block_minutes' => 10,    // 登录失败多次后封禁时间更长
        ],
        '/api/auth/register' => [
            'max_attempts' => 5,
            'decay_minutes' => 1,
            'block_minutes' => 15,
        ],
    ];

    /**
     * 构造函数.
     */
    public function __construct()
    {
        // 获取Redis实例
        $this->redis = $this->redisFactory->get('default');
    }

    /**
     * 处理请求，实现访问频率限制.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // 获取请求信息
            $path = $request->getUri()->getPath();
            $method = $request->getMethod();
            $ip = $request->getServerParams()['remote_addr'] ?? 'unknown';

            // 对静态资源和某些特定路径跳过频率限制
            if ($this->shouldSkipRateLimit($path)) {
                return $handler->handle($request);
            }

            // 生成访问键
            $key = $this->generateKey($ip, $path, $method);
            $blockKey = $key . ':blocked';

            // 获取配置
            $config = $this->getConfigForPath($path);

            // 检查是否处于封禁状态
            if ($this->isBlocked($blockKey)) {
                $remainingBlockTime = $this->getRemainingBlockTime($blockKey);
                return $this->createRateLimitResponse($remainingBlockTime);
            }

            // 检查访问频率
            if ($this->exceedsRateLimit($key, $config['max_attempts'])) {
                // 记录封禁
                $this->blockRequest($blockKey, $config['block_minutes']);
                $this->logger->warning('API访问频率超限，IP已被临时封禁', [
                    'ip' => $ip,
                    'path' => $path,
                    'method' => $method,
                    'block_minutes' => $config['block_minutes'],
                ]);
                return $this->createRateLimitResponse($config['block_minutes'] * 60);
            }

            // 增加访问计数
            $this->incrementCount($key, $config['decay_minutes']);

            // 继续处理请求
            $response = $handler->handle($request);

            // 添加速率限制头信息
            $attempts = $this->getCurrentAttempts($key);
            return $this->addRateLimitHeaders($response, $config['max_attempts'], $attempts, $config['decay_minutes']);
        } catch (Throwable $e) {
            $this->logger->error('访问频率限制中间件异常', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 200),
            ]);
            // 如果中间件出现异常，不影响正常请求处理
            return $handler->handle($request);
        }
    }

    /**
     * 判断是否应该跳过频率限制.
     */
    protected function shouldSkipRateLimit(string $path): bool
    {
        // 跳过静态资源路径
        $skipPatterns = [
            '/^\/static\//',
            '/^\/images\//',
            '/^\/css\//',
            '/^\/js\//',
            '/^\/favicon.ico$/',
        ];

        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 为特定路径获取配置.
     */
    protected function getConfigForPath(string $path): array
    {
        // 检查是否有路径特定配置
        foreach ($this->pathSpecificConfig as $pattern => $config) {
            if (strpos($path, $pattern) === 0) {
                return array_merge($this->defaultConfig, $config);
            }
        }

        return $this->defaultConfig;
    }

    /**
     * 生成Redis键名.
     */
    protected function generateKey(string $ip, string $path, string $method): string
    {
        // 移除查询参数，只基于路径和方法
        $cleanPath = parse_url($path, PHP_URL_PATH) ?? $path;
        return sprintf('rate_limit:%s:%s:%s', $ip, $method, md5($cleanPath));
    }

    /**
     * 增加访问计数.
     */
    protected function incrementCount(string $key, int $decayMinutes): void
    {
        $this->redis->multi();
        $this->redis->incr($key);
        // 设置过期时间
        $this->redis->expire($key, $decayMinutes * 60);
        $this->redis->exec();
    }

    /**
     * 获取当前访问次数.
     */
    protected function getCurrentAttempts(string $key): int
    {
        $count = $this->redis->get($key);
        return $count ? (int) $count : 0;
    }

    /**
     * 检查是否超过访问限制.
     */
    protected function exceedsRateLimit(string $key, int $maxAttempts): bool
    {
        $current = $this->getCurrentAttempts($key);
        return $current >= $maxAttempts;
    }

    /**
     * 封禁请求
     */
    protected function blockRequest(string $blockKey, int $blockMinutes): void
    {
        $this->redis->setex($blockKey, $blockMinutes * 60, 1);
    }

    /**
     * 检查是否处于封禁状态
     */
    protected function isBlocked(string $blockKey): bool
    {
        return (bool) $this->redis->exists($blockKey);
    }

    /**
     * 获取剩余封禁时间（秒）.
     */
    protected function getRemainingBlockTime(string $blockKey): int
    {
        return (int) $this->redis->ttl($blockKey);
    }

    /**
     * 创建频率限制响应.
     */
    protected function createRateLimitResponse(int $retryAfter): ResponseInterface
    {
        $response = ApplicationContext::getContainer()->get(ResponseInterface::class);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Retry-After', (string) $retryAfter)
            ->withStatus(429)
            ->withBody(new SwooleStream(json_encode([
                'code' => StatusCode::TOO_MANY_REQUESTS,
                'message' => '请求过于频繁，请稍后再试',
                'data' => [
                    'retry_after' => $retryAfter,
                ],
            ], JSON_UNESCAPED_UNICODE)));
    }

    /**
     * 添加速率限制头信息.
     */
    protected function addRateLimitHeaders(ResponseInterface $response, int $limit, int $remaining, int $resetMinutes): ResponseInterface
    {
        $resetTime = time() + ($resetMinutes * 60);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $limit)
            ->withHeader('X-RateLimit-Remaining', (string) ($limit - $remaining))
            ->withHeader('X-RateLimit-Reset', (string) $resetTime);
    }
}
