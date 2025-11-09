\u003c?php

namespace App\Controller\Api;

use App\Service\NodeLinksService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

/**
 * 节点链接控制器
 * 处理节点链接相关的API请求
 */
/** @Controller */
class NodeLinksController extends AbstractController
{
    /**
     * @Inject
     * @var NodeLinksService
     */
    protected $nodeLinksService;

    /**
     * 创建节点链接
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    /** @RequestMapping(path="/api/node-links", methods={"POST"}) */
    public function create(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $data = $request-\u003eall();
            $creatorId = $this-\u003egetCurrentUserId();
            
            $link = $this-\u003enodeLinksService-\u003ecreateLink($data, $creatorId);
            
            return $this-\u003esuccess([
                'data' =u003e $link,
                'message' =u003e '创建节点链接成功'
            ]);
        } catch (\Exception $e) {
            return $this-\u003efail($e-\u003egetMessage());
        }
    }

    /**
     * 批量创建节点链接
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    /** @RequestMapping(path="/api/node-links/batch", methods={"POST"}) */
    public function batchCreate(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $data = $request-\u003eall();
            $linksData = $data['links'] ?? [];
            $creatorId = $this-\u003egetCurrentUserId();
            
            if (empty($linksData)) {
                return $this-\u003efail('请提供链接数据');
            }
            
            $results = $this-\u003enodeLinksService-\u003ebatchCreateLinks($linksData, $creatorId);
            
            return $this-\u003esuccess([
                'data' =u003e $results,
                'message' =u003e '批量创建节点链接成功',
                'created_count' =u003e count($results)
            ]);
        } catch (\Exception $e) {
            return $this-\u003efail($e-\u003egetMessage());
        }
    }

    /**
     * 更新节点链接
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    /** @RequestMapping(path="/api/node-links/{id}", methods={"PUT"}) */
    public function update(RequestInterface $request, ResponseInterface $response, int $id)
    {
        try {
            $data = $request-\u003eall();
            $creatorId = $this-\u003egetCurrentUserId();
            
            $success = $this-\u003enodeLinksService-\u003eupdateLink($id, $data, $creatorId);
            
            if ($success) {
                return $this-\u003esuccess([
                    'message' =u003e '更新节点链接成功'
                ]);
            } else {
                return $this-\u003efail('更新节点链接失败');
            }
        } catch (\Exception $e) {
            return $this-\u003efail($e-\u003egetMessage());
        }
    }

    /**
     * 删除节点链接
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    /** @RequestMapping(path="/api/node-links/{id}", methods={"DELETE"}) */
    public function delete(RequestInterface $request, ResponseInterface $response, int $id)
    {
        try {
            $creatorId = $this-\u003egetCurrentUserId();
            
            $success = $this-\u003enodeLinksService-\u003edeleteLink($id, $creatorId);
            
            if ($success) {
                return $this-\u003esuccess([
                    'message' =u003e '删除节点链接成功'
                ]);
            } else {
                return $this-\u003efail('删除节点链接失败');
            }
        } catch (\Exception $e) {
            return $this-\u003efail($e-\u003egetMessage());
        }
    }

    /**
     * 获取脑图的所有链接
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    /** @RequestMapping(path="/api/mindmaps/{rootId}/node-links", methods={"GET"}) */
    public function getLinksByRootId(RequestInterface $request, ResponseInterface $response, int $rootId)
    {
        try {
            $links = $this-\u003enodeLinksService-\u003 egetLinksByRootId($rootId);
            
            return $this-\u003esuccess([
                'data' =u003e $links,
                'total' =u003e count($links)
            ]);
        } catch (\Exception $e) {
            return $this-\u003efail($e-\u003egetMessage());
        }
    }

    /**
     * 获取当前用户ID
     * @return int
     */
    protected function getCurrentUserId(): int
    {
        // 这里应该从JWT token或session中获取用户ID
        // 暂时返回固定值，实际使用时需要根据认证机制调整
        return 1;
    }
}