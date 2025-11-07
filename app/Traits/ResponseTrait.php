<?php

declare(strict_types=1);
/**
 * 响应处理Trait
 * 提供统一的API响应格式
 */
namespace App\Traits;

use App\Constants\ResponseMessage;
use App\Constants\StatusCode;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Utils\ApplicationContext;
use Hyperf\HttpMessage\Stream\SwooleStream;

/**
 * @property ResponseInterface $response
 */
trait ResponseTrait
{
    /**
     * 成功响应
     * @param mixed $data 响应数据
     * @param string $message 响应消息
     * @param int $code 状态码
     * @return ResponseInterface
     */
    protected function success($data = null, string $message = '', int $code = StatusCode::SUCCESS): ResponseInterface
    {
        $response = $this->getResponse();
        
        // 如果未提供消息，使用默认消息
        if (empty($message)) {
            $message = ResponseMessage::getDefaultMessage($code);
        }
        
        $result = [
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ];
        
        $jsonResult = json_encode($result, JSON_UNESCAPED_UNICODE);
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($code)
            ->withBody(new SwooleStream($jsonResult));
    }
    
    /**
     * 失败响应
     * @param int $code 状态码
     * @param string $message 响应消息
     * @param mixed $data 响应数据
     * @return ResponseInterface
     */
    protected function fail(int $code = StatusCode::INTERNAL_SERVER_ERROR, string $message = '', $data = null): ResponseInterface
    {
        $response = $this->getResponse();
        
        // 如果未提供消息，使用默认消息
        if (empty($message)) {
            $message = ResponseMessage::getDefaultMessage($code);
        }
        
        $result = [
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ];
        
        $jsonResult = json_encode($result, JSON_UNESCAPED_UNICODE);
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($code)
            ->withBody(new SwooleStream($jsonResult));
    }
    
    /**
     * 获取响应实例
     * @return ResponseInterface
     */
    protected function getResponse(): ResponseInterface
    {
        // 如果当前对象已经有response属性，则直接返回
        if (property_exists($this, 'response')) {
            return $this->response;
        }
        
        // 否则从容器中获取
        return ApplicationContext::getContainer()->get(ResponseInterface::class);
    }
    
    /**
     * 返回分页响应
     * @param array $data 数据列表
     * @param array $meta 分页元数据
     * @param string $message 响应消息
     * @param int $code 状态码
     * @return ResponseInterface
     */
    protected function paginate(array $data, array $meta, string $message = '', int $code = StatusCode::SUCCESS): ResponseInterface
    {
        $responseData = [
            'items' => $data,
            'meta' => $meta,
        ];
        
        return $this->success($responseData, $message, $code);
    }
}