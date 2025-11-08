<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Service\SystemService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use App\Constants\StatusCode;
use App\Traits\LogTrait;

/**
 * @Controller(prefix="/api/system")
 */
class SystemController extends AbstractController
{
    use LogTrait;
    
    /**
     * @Inject
     * @var SystemService
     */
    protected $systemService;

    /**
     * 获取系统配置
     * 
     * @RequestMapping(path="/config", methods={"GET"})
     */
    public function getSystemConfig(): ResponseInterface
    {
        try {
            $config = $this->systemService->getSystemConfig();
            return $this->success($config);
        } catch (\Exception $e) {
            $this->logError('获取系统配置异常', ['message' => $e->getMessage()], $e, 'system');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取系统配置失败');
        }
    }

    /**
     * 获取统计数据
     * 
     * @RequestMapping(path="/statistics", methods={"GET"})
     */
    public function getStatistics(RequestInterface $request): ResponseInterface
    {
        try {
            $params = $request->all();
            $statistics = $this->systemService->getStatistics($params);
            return $this->success($statistics);
        } catch (\Exception $e) {
            $this->logError('获取统计数据异常', ['message' => $e->getMessage()], $e, 'system');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取统计数据失败');
        }
    }
}