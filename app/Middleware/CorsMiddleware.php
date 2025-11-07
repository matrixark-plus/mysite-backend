<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CORS中间件
 * 使用Hyperf框架内置组件实现跨域资源共享
 */
class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var HttpResponse
     */
    protected $response;

    public function __construct(ContainerInterface $container, HttpResponse $response)
    {
        $this->container = $container;
        $this->response = $response;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 预检请求直接返回
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handleOption();
        }

        $response = $handler->handle($request);

        return $this->addHeaders($response);
    }

    /**
     * 处理预检请求
     */
    protected function handleOption(): ResponseInterface
    {
        return $this->addHeaders($this->response->raw());
    }

    /**
     * 添加CORS头信息
     */
    protected function addHeaders(ResponseInterface $response): ResponseInterface
    {
        $origin = '*'; // 允许所有来源，生产环境应该配置具体域名
        
        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, X-Requested-With')
            ->withHeader('Access-Control-Max-Age', '86400'); // 预检请求结果缓存24小时
    }
}