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
use Hyperf\Logger\LoggerInterface;
use App\Constants\StatusCode;

/**
 * @Controller(prefix="/api/system")
 */
class SystemController extends AbstractController
{
    use \App\Traits\LogTrait;
    /**
     * @Inject
     * @var SystemService
     */
    protected $systemService;
    
    // Logger通过LogTrait获取，不需要在此注入
    
    /**
     * 获取统计数据
     * 
     * @RequestMapping(path="/statistics", methods={"GET"})
     */
    public function getStatistics(RequestInterface $request): ResponseInterface
    {
        try {
            // 确保systemService已正确注入
            if (!$this->systemService) {
                throw new \Exception('SystemService未正确初始化');
            }
            
            $params = $request->all();
            $statistics = $this->systemService->getStatistics($params);
            return $this->success($statistics);
        } catch (\Exception $e) {
            // 使用LogTrait记录异常日志
            $this->logError('获取统计数据异常', [
                'message' => $e->getMessage(),
                'exception' => get_class($e)
            ], $e, 'system');
            
            // 如果是服务未初始化的错误，提供临时的默认统计数据作为备选方案
            if (strpos($e->getMessage(), 'SystemService未正确初始化') !== false || 
                strpos($e->getMessage(), 'Call to a member function') !== false) {
                // 返回默认的统计数据，避免前端完全无法展示
                $defaultStats = [
                    'user_count' => 0,
                    'article_count' => 0,
                    'comment_count' => 0,
                    'view_count' => 0
                ];
                return $this->success($defaultStats);
            }
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取统计数据失败');
        }
    }
}