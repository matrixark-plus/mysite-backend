<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Context\Context;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Utils\Codec\Json;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 请求体解析中间件
 * 用于解析JSON、表单等请求体数据
 */
class RequestBodyParserMiddleware implements MiddlewareInterface
{
    /**
     * @Inject
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @Inject
     * @var StdoutLoggerInterface
     */
    protected $logger;

    /**
     * 支持的内容类型
     * @var array
     */
    protected $supportedContentTypes = [
        'application/json',
        'application/x-www-form-urlencoded',
    ];

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentType = $this->getContentType($request);
        
        // 检查是否支持的内容类型
        if (!empty($contentType) && in_array($contentType, $this->supportedContentTypes, true)) {
            try {
                // 解析请求体
                $request = $this->parseBody($request, $contentType);
                // 将解析后的请求重新设置到上下文
                Context::set(ServerRequestInterface::class, $request);
            } catch (\Throwable $e) {
                $this->logger->warning('请求体解析失败: ' . $e->getMessage());
                // 解析失败时不中断请求，继续处理
            }
        }
        
        return $handler->handle($request);
    }

    /**
     * 获取内容类型
     * @param ServerRequestInterface $request
     * @return string
     */
    protected function getContentType(ServerRequestInterface $request): string
    {
        $contentType = $request->getHeaderLine('Content-Type');
        if ($contentType) {
            $parts = explode(';', $contentType);
            return strtolower(trim($parts[0]));
        }
        return '';
    }

    /**
     * 解析请求体
     * @param ServerRequestInterface $request
     * @param string $contentType
     * @return ServerRequestInterface
     */
    protected function parseBody(ServerRequestInterface $request, string $contentType): ServerRequestInterface
    {
        $body = $request->getBody();
        if ($body->getSize() === 0) {
            return $request;
        }

        // 保存原始请求体内容
        $bodyContent = (string)$body;
        
        // 重新创建流，因为读取后指针会移动到末尾
        $request = $request->withBody(new SwooleStream($bodyContent));

        try {
            if ($contentType === 'application/json') {
                // 解析JSON数据
                $parsedData = Json::decode($bodyContent);
                if (is_array($parsedData)) {
                    $request = $request->withParsedBody($parsedData);
                }
            } elseif ($contentType === 'application/x-www-form-urlencoded') {
                // 解析表单数据
                parse_str($bodyContent, $parsedData);
                if (is_array($parsedData)) {
                    $request = $request->withParsedBody($parsedData);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('解析' . $contentType . '请求体失败: ' . $e->getMessage());
        }

        return $request;
    }
}