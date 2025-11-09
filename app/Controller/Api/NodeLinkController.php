\u003c?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller\Api;

use App\Service\NodeLinkService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

/**
 * 节点链接控制器
 * 处理思维导图节点链接相关的HTTP请求.
 */
#[Controller(prefix: '/api/mindmaps/{mindmapId}/links')]
class NodeLinkController extends AbstractController
{
    /**
     * @Inject
     * @var NodeLinkService
     */
    protected $nodeLinkService;

    /**
     * 创建节点链接
     *
     * @param int $mindmapId 思维导图ID
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/', methods: ['POST'])]
    public function create(int $mindmapId, RequestInterface $request)
    {
        try {
            // 获取当前用户ID
            $userId = $this-\u003egetCurrentUserId();
            if (! $userId) {
                return $this-\u003eunauthorized('请先登录');
            }
            
            $data = $request-\u003eall();
            
            $result = $this-\u003enodeLinkService-\u003 ecreateNodeLink($userId, $data);
            
            if ($result['success']) {
                return $this-\u003esuccess('创建成功', $result['data']);
            } else {
                if ($result['message'] === '无权限操作此思维导图') {
                    return $this-\u003eforbidden($result['message']);
                }
                if ($result['message'] === '源节点或目标节点不存在') {
                    return $this-\u003enotFound($result['message']);
                }
                return $this-\u003eerror($result['message'], [], 400);
            }
        } catch (\Exception $e) {
            return $this-\u003eserverError($e-\u003egetMessage());
        }
    }

    /**
     * 批量创建节点链接
     *
     * @param int $mindmapId 思维导图ID
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/batch', methods: ['POST'])]
    public function batchCreate(int $mindmapId, RequestInterface $request)
    {
        try {
            // 获取当前用户ID
            $userId = $this-\u003egetCurrentUserId();
            if (! $userId) {
                return $this-\u003eunauthorized('请先登录');
            }
            
            $data = $request-\u003eall();
            $links = $data['links'] ?? [];
            
            if (! is_array($links)) {
                return $this-\u003eerror('链接数据必须是数组格式', [], 400);
            }
            
            $result = $this-\u003enodeLinkService-\u003 ebatchCreateNodeLinks($userId, $links);
            
            if ($result['success']) {
                return $this-\u003esuccess('批量创建成功', $result['data']);
            } else {
                if ($result['message'] === '无权限操作此思维导图') {
                    return $this-\u003eforbidden($result['message']);
                }
                return $this-\u003eerror($result['message'], [], 400);
            }
        } catch (\Exception $e) {
            return $this-\u003eserverError($e-\u003egetMessage());
        }
    }

    /**
     * 获取思维导图的所有节点链接
     *
     * @param int $mindmapId 思维导图ID
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/', methods: ['GET'])]
    public function list(int $mindmapId)
    {
        try {
            // 获取当前用户ID（可能为null）
            $userId = $this-\u003egetCurrentUserId();
            
            $result = $this-\u003enodeLinkService-\u003 egetMindmapLinks($userId, $mindmapId);
            
            if ($result['success']) {
                return $this-\u003esuccess('获取成功', $result['data']);
            } else {
                if ($result['message'] === '无权限查看此思维导图') {
                    return $this-\u003eforbidden($result['message']);
                }
                return $this-\u003eerror($result['message'], [], 400);
            }
        } catch (\Exception $e) {
            return $this-\u003eserverError($e-\u003egetMessage());
        }
    }

    /**
     * 删除节点链接
     *
     * @param int $mindmapId 思维导图ID
     * @param int $linkId 链接ID
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/{linkId}', methods: ['DELETE'])]
    public function delete(int $mindmapId, int $linkId)
    {
        try {
            // 获取当前用户ID
            $userId = $this-\u003egetCurrentUserId();
            if (! $userId) {
                return $this-\u003eunauthorized('请先登录');
            }
            
            $result = $this-\u003enodeLinkService-\u003 edeleteNodeLink($userId, $linkId);
            
            if ($result['success']) {
                return $this-\u003esuccess('删除成功');
            } else {
                if ($result['message'] === '链接不存在') {
                    return $this-\u003enotFound($result['message']);
                }
                if ($result['message'] === '无权限操作此链接') {
                    return $this-\u003eforbidden($result['message']);
                }
                return $this-\u003eerror($result['message'], [], 400);
            }
        } catch (\Exception $e) {
            return $this-\u003eserverError($e-\u003egetMessage());
        }
    }
    
    /**
     * 获取当前登录用户的ID
     * 实际项目中应该从JWT token或session中获取
     *
     * @return int|null 用户ID
     */
    protected function getCurrentUserId(): ?int
    {
        // 这里只是模拟，实际项目中应该实现真实的用户认证逻辑
        return $this-\u003erequest-\u003einput('user_id', null);
    }
}