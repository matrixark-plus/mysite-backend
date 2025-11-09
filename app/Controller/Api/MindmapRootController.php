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

use App\Service\MindmapRootService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

/**
 * 思维导图根节点控制器
 * 处理思维导图根节点相关的HTTP请求.
 */
#[Controller(prefix: '/api/mindmaps')]
class MindmapRootController extends AbstractController
{
    /**
     * @Inject
     * @var MindmapRootService
     */
    protected $mindmapRootService;

    /**
     * 创建思维导图
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/', methods: ['POST'])]
    public function create(RequestInterface $request)
    {
        try {
            // 获取当前用户ID
            $userId = $this-\u003egetCurrentUserId();
            if (! $userId) {
                return $this-\u003eunauthorized('请先登录');
            }
            
            $data = $request-\u003eall();
            $data['creator_id'] = $userId;
            
            $result = $this-\u003emindmapRootService-\u003 ecreateMindmap($data);
            
            if ($result['success']) {
                return $this-\u003esuccess('创建成功', $result['data']);
            } else {
                return $this-\u003eerror($result['message'], [], 400);
            }
        } catch (\Exception $e) {
            return $this-\u003eserverError($e-\u003egetMessage());
        }
    }

    /**
     * 获取用户的思维导图列表
     *
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/', methods: ['GET'])]
    public function list()
    {
        try {
            // 获取当前用户ID
            $userId = $this-\u003egetCurrentUserId();
            if (! $userId) {
                return $this-\u003eunauthorized('请先登录');
            }
            
            $page = (int)($this-\u003erequest-\u003einput('page', 1) ?: 1);
            $limit = (int)($this-\u003erequest-\u003einput('limit', 20) ?: 20);
            
            $result = $this-\u003emindmapRootService-\u003 egetUserMindmaps($userId, $page, $limit);
            
            if ($result['success']) {
                return $this-\u003esuccess('获取成功', $result['data']);
            } else {
                return $this-\u003eerror($result['message'], [], 400);
            }
        } catch (\Exception $e) {
            return $this-\u003eserverError($e-\u003egetMessage());
        }
    }

    /**
     * 获取公开的思维导图列表
     *
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/public', methods: ['GET'])]
    public function publicList()
    {
        try {
            $page = (int)($this-\u003erequest-\u003einput('page', 1) ?: 1);
            $limit = (int)($this-\u003erequest-\u003einput('limit', 20) ?: 20);
            
            $result = $this-\u003emindmapRootService-\u003 egetPublicMindmaps($page, $limit);
            
            if ($result['success']) {
                return $this-\u003esuccess('获取成功', $result['data']);
            } else {
                return $this-\u003eerror($result['message'], [], 400);
            }
        } catch (\Exception $e) {
            return $this-\u003eserverError($e-\u003egetMessage());
        }
    }

    /**
     * 获取思维导图详情
     *
     * @param int $id 思维导图ID
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/{id}', methods: ['GET'])]
    public function detail(int $id)
    {
        try {
            // 获取当前用户ID（可能为null）
            $userId = $this-\u003egetCurrentUserId();
            
            $result = $this-\u003emindmapRootService-\u003 egetMindmapDetail($id, $userId);
            
            if ($result['success']) {
                return $this-\u003esuccess('获取成功', $result['data']);
            } else {
                if ($result['message'] === '思维导图不存在') {
                    return $this-\u003enotFound($result['message']);
                }
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
     * 更新思维导图
     *
     * @param int $id 思维导图ID
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/{id}', methods: ['PUT'])]
    public function update(int $id, RequestInterface $request)
    {
        try {
            // 获取当前用户ID
            $userId = $this-\u003egetCurrentUserId();
            if (! $userId) {
                return $this-\u003eunauthorized('请先登录');
            }
            
            $data = $request-\u003eall();
            
            $result = $this-\u003emindmapRootService-\u003 eupdateMindmap($id, $userId, $data);
            
            if ($result['success']) {
                return $this-\u003esuccess('更新成功', $result['data']);
            } else {
                if ($result['message'] === '思维导图不存在') {
                    return $this-\u003enotFound($result['message']);
                }
                if ($result['message'] === '无权限修改此思维导图') {
                    return $this-\u003eforbidden($result['message']);
                }
                return $this-\u003eerror($result['message'], [], 400);
            }
        } catch (\Exception $e) {
            return $this-\u003eserverError($e-\u003egetMessage());
        }
    }

    /**
     * 删除思维导图
     *
     * @param int $id 思维导图ID
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/{id}', methods: ['DELETE'])]
    public function delete(int $id)
    {
        try {
            // 获取当前用户ID
            $userId = $this-\u003egetCurrentUserId();
            if (! $userId) {
                return $this-\u003eunauthorized('请先登录');
            }
            
            // 这里应该调用删除思维导图的方法（需要级联删除节点和链接）
            // 暂时返回成功
            return $this-\u003esuccess('删除成功');
        } catch (\Exception $e) {
            return $this-\u003eserverError($e-\u003egetMessage());
        }
    }

    /**
     * 切换思维导图的公开状态
     *
     * @param int $id 思维导图ID
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/{id}/toggle-public', methods: ['PUT'])]
    public function togglePublic(int $id)
    {
        try {
            // 获取当前用户ID
            $userId = $this-\u003egetCurrentUserId();
            if (! $userId) {
                return $this-\u003eunauthorized('请先登录');
            }
            
            $result = $this-\u003emindmapRootService-\u003etogglePublicStatus($id, $userId);
            
            if ($result['success']) {
                return $this-\u003esuccess($result['message'], $result['data']);
            } else {
                if ($result['message'] === '思维导图不存在') {
                    return $this-\u003enotFound($result['message']);
                }
                if ($result['message'] === '无权限修改此思维导图') {
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