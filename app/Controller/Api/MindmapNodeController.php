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

use App\Controller\Api\Validator\MindmapNodeValidator;
use App\Service\MindmapNodeService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\ValidationException;

/**
 * 思维导图节点控制器
 * 处理思维导图节点相关的HTTP请求.
 */
/**
 * @Controller(prefix="/api/mindmaps/{mindmapId}/nodes")
 */
class MindmapNodeController extends AbstractController
{
    /**
     * @Inject
     * @var MindmapNodeService
     */
    protected $mindmapNodeService;
    
    /**
     * @Inject
     * @var MindmapNodeValidator
     */
    protected $mindmapNodeValidator;

    /**
     * 创建节点
     *
     * @param int $mindmapId 思维导图ID
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    /**
     * @RequestMapping(path="/", methods={"POST"})
     */
    public function create(int $mindmapId, RequestInterface $request)
    {
        try {
            // 获取当前用户ID
            $userId = $this-\u003egetCurrentUserId();
            if (! $userId) {
                return $this-\u003eunauthorized('请先登录');
            }
            
            // 验证思维导图ID
            $this-\u003emindmapNodeValidator-\u003evalidateMindmapId($mindmapId);
            
            $data = $request-\u003eall();
            $data['root_id'] = $mindmapId;
            // 验证参数
            $validatedData = $this-\u003emindmapNodeValidator-\u003evalidateCreateNode($data);
            
            $result = $this-\u003emindmapNodeService-\u003 ecreateNode($userId, $validatedData);
            
            if ($result['success']) {
                return $this-\u003esuccess('创建成功', $result['data']);
            } else {
                if ($result['message'] === '无权限操作此思维导图') {
                    return $this-\u003eforbidden($result['message']);
                }
                return $this-\u003eerror($result['message'], [], 400);
            }
        } catch (ValidationException $e) {
            return $this-\u003eerror('参数验证失败', $e-\u003evalidator-\u003eerrors()-\u003eall(), 400);
        } catch (\Exception $e) {
            return $this-\u003eserverError($e-\u003egetMessage());
        }
    }

    /**
     * 批量创建节点
     *
     * @param int $mindmapId 思维导图ID
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    /**
     * @RequestMapping(path="/batch", methods={"POST"})
     */
    public function batchCreate(int $mindmapId, RequestInterface $request)
    {
        try {
            // 获取当前用户ID
            $userId = $this-\u003egetCurrentUserId();
            if (! $userId) {
                return $this-\u003eunauthorized('请先登录');
            }
            
            // 验证思维导图ID
            $this-\u003emindmapNodeValidator-\u003evalidateMindmapId($mindmapId);
            
            $data = $request-\u003eall();
            // 为所有节点设置root_id
            $data['nodes'] = array_map(function($node) use ($mindmapId) {
                $node['root_id'] = $mindmapId;
                return $node;
            }, $data['nodes'] ?? []);
            
            // 验证参数
            $validatedData = $this-\u003emindmapNodeValidator-\u003evalidateBatchCreateNodes($data);
            
            $result = $this-\u003emindmapNodeService-\u003 ebatchCreateNodes($userId, $validatedData['nodes']);
            
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
     * 获取思维导图的所有节点
     *
     * @param int $mindmapId 思维导图ID
     * @return ResponseInterface
     */
    /**
     * @RequestMapping(path="/", methods={"GET"})
     */
    public function list(int $mindmapId)
    {
        try {
            // 验证思维导图ID
            $this-\u003emindmapNodeValidator-\u003evalidateMindmapId($mindmapId);
            
            // 获取当前用户ID（可能为null）
            $userId = $this-\u003egetCurrentUserId();
            
            $result = $this-\u003emindmapNodeService-\u003 egetMindmapNodes($userId, $mindmapId);
            
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
     * 更新节点
     *
     * @param int $mindmapId 思维导图ID
     * @param int $nodeId 节点ID
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    /**
     * @RequestMapping(path="/{nodeId}", methods={"PUT"})
     */
    public function update(int $mindmapId, int $nodeId, RequestInterface $request)
    {
        try {
            // 获取当前用户ID
            $userId = $this-\u003egetCurrentUserId();
            if (! $userId) {
                return $this-\u003eunauthorized('请先登录');
            }
            
            // 验证思维导图ID和节点ID
            $this-\u003emindmapNodeValidator-\u003evalidateMindmapId($mindmapId);
            $this-\u003emindmapNodeValidator-\u003evalidateNodeId($nodeId);
            
            $data = $request-\u003eall();
            // 验证参数
            $validatedData = $this-\u003emindmapNodeValidator-\u003evalidateUpdateNode($data);
            
            $result = $this-\u003emindmapNodeService-\u003 eupdateNode($userId, $nodeId, $validatedData);
            
            if ($result['success']) {
                return $this-\u003esuccess('更新成功', $result['data']);
            } else {
                if ($result['message'] === '节点不存在') {
                    return $this-\u003enotFound($result['message']);
                }
                if ($result['message'] === '无权限操作此节点') {
                    return $this-\u003eforbidden($result['message']);
                }
                return $this-\u003eerror($result['message'], [], 400);
            }
        } catch (\Exception $e) {
            return $this-\u003eserverError($e-\u003egetMessage());
        }
    }

    /**
     * 删除节点
     *
     * @param int $mindmapId 思维导图ID
     * @param int $nodeId 节点ID
     * @return ResponseInterface
     */
    /**
     * @RequestMapping(path="/{nodeId}", methods={"DELETE"})
     */
    public function delete(int $mindmapId, int $nodeId)
    {
        try {
            // 获取当前用户ID
            $userId = $this-\u003egetCurrentUserId();
            if (! $userId) {
                return $this-\u003eunauthorized('请先登录');
            }
            
            // 验证思维导图ID和节点ID
            $this-\u003emindmapNodeValidator-\u003evalidateMindmapId($mindmapId);
            $this-\u003emindmapNodeValidator-\u003evalidateNodeId($nodeId);
            
            $result = $this-\u003emindmapNodeService-\u003 edeleteNode($userId, $nodeId);
            
            if ($result['success']) {
                return $this-\u003esuccess('删除成功');
            } else {
                if ($result['message'] === '节点不存在') {
                    return $this-\u003enotFound($result['message']);
                }
                if ($result['message'] === '无权限操作此节点') {
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