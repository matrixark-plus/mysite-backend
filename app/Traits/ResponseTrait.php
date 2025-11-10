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

namespace App\Traits;

use App\Constants\ResponseMessage;
use App\Constants\StatusCode;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Utils\ApplicationContext;

/**
 * @property ResponseInterface $response
 */
trait ResponseTrait
{
    /**
     * 成功响应.
     * @param mixed $data 响应数据
     * @param string $message 响应消息
     * @param int $code 状态码
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
     * 失败响应.
     * @param int $code 状态码
     * @param string $message 响应消息
     * @param mixed $data 响应数据
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
     * 获取响应实例.
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
     * 返回分页响应.
     * @param array $data 数据列表
     * @param array $meta 分页元数据
     * @param string $message 响应消息
     * @param int $code 状态码
     */
    protected function paginate(array $data, array $meta, string $message = '', int $code = StatusCode::SUCCESS): ResponseInterface
    {
        $responseData = [
            'items' => $data,
            'meta' => $meta,
        ];

        return $this->success($responseData, $message, $code);
    }

    /**
     * 错误响应的简化版本，默认为500错误.
     * @param string $message 错误消息
     * @param mixed $data 响应数据
     */
    protected function error(string $message = '', $data = null): ResponseInterface
    {
        return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, $message, $data);
    }

    /**
     * 验证错误响应.
     * @param string $message 错误消息
     * @param mixed $data 响应数据
     */
    protected function validationError(string $message = '', $data = null): ResponseInterface
    {
        return $this->fail(StatusCode::VALIDATION_ERROR, $message, $data);
    }

    /**
     * 未授权响应.
     * @param string $message 错误消息
     * @param mixed $data 响应数据
     */
    protected function unauthorized(string $message = '', $data = null): ResponseInterface
    {
        return $this->fail(StatusCode::UNAUTHORIZED, $message, $data);
    }

    /**
     * 资源不存在响应.
     * @param string $message 错误消息
     * @param mixed $data 响应数据
     */
    protected function notFound(string $message = '', $data = null): ResponseInterface
    {
        return $this->fail(StatusCode::NOT_FOUND, $message, $data);
    }

    /**
     * 禁止访问响应.
     * @param string $message 错误消息
     * @param mixed $data 响应数据
     */
    protected function forbidden(string $message = '', $data = null): ResponseInterface
    {
        return $this->fail(StatusCode::FORBIDDEN, $message, $data);
    }

    /**
     * 服务器错误响应.
     * @param string $message 错误消息
     * @param mixed $data 响应数据
     */
    protected function serverError(string $message = '', $data = null): ResponseInterface
    {
        return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, $message, $data);
    }
}
