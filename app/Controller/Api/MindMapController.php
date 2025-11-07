<?php

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Service\MindMapService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

/**
 * @Controller(prefix="/api/mind-map")
 */
class MindMapController extends AbstractController
{
    /**
     * @Inject
     * @var MindMapService
     */
    protected $mindMapService;
    
    /**
     * 获取根节点列表
     * 
     * @RequestMapping(path="/root-nodes", methods={"GET"})
     */
    public function getRootNodes(RequestInterface $request)
    {
        try {
            $params = $request->all();
            $result = $this->mindMapService->getRootNodes($params);
            return $this->success($result);
        } catch (\Exception $e) {
            logger()->error('获取脑图根节点列表异常: ' . $e->getMessage());
            return $this->fail(500, '获取脑图列表失败');
        }
    }
    
    /**
     * 获取脑图数据
     * 
     * @RequestMapping(path="/{id}", methods={"GET"})
     */
    public function getMindMapData($id, RequestInterface $request)
    {
        try {
            $includeContent = (bool)$request->input('include_content', false);
            $result = $this->mindMapService->getMindMapData($id, $includeContent);
            return $this->success($result);
        } catch (\Exception $e) {
            logger()->error('获取脑图数据异常: ' . $e->getMessage());
            return $this->fail(404, $e->getMessage() ?: '脑图不存在');
        }
    }
}