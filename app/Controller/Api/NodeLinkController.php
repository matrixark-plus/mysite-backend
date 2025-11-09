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

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Controller\Api\Validator\NodeLinkValidator;
use App\Service\NodeLinkService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Validation\ValidationException;

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
     * @Inject
     * @var NodeLinkValidator
     */
    protected $validator;

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
            $userId = $this->getCurrentUserId();
            if (! $userId) {
                return $this->unauthorized('请先登录');
            }
            
            // 验证思维导图ID
            $this->validator->validateMindmapId($mindmapId);
            
            $data = $request->all();
            // 确保data中包含mindmapId
            $data['mindmap_id'] = $mindmapId;
            
            // 验证创建节点链接的参数
            $this->validator->validateCreateNodeLink($data);
            
            $result = $this->nodeLinkService->createNodeLink($userId, $data);
            
            if ($result['success']) {
                return $this->success($result['data'], '创建成功');
            } else {
                if ($result['message'] === '无权限操作此思维导图') {
                    return $this->error($result['message']);
                }
                if ($result['message'] === '源节点或目标节点不存在') {
                    return $this->notFound($result['message']);
                }
                return $this->error($result['message']);
            }
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage());
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
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
            $userId = $this->getCurrentUserId();
            if (! $userId) {
                return $this->unauthorized('请先登录');
            }
            
            // 验证思维导图ID
            $this->validator->validateMindmapId($mindmapId);
            
            $data = $request->all();
            $links = $data['links'] ?? [];
            
            // 验证批量创建节点链接的数据
            $this->validator->validateBatchCreateNodeLinks($links);
            
            // 确保所有链接都包含mindmapId
            foreach ($links as &$link) {
                $link['mindmap_id'] = $mindmapId;
            }
            
            $result = $this->nodeLinkService->batchCreateNodeLinks($userId, $links);
            
            if ($result['success']) {
                return $this->success($result['data'], '批量创建成功');
            } else {
                if ($result['message'] === '无权限操作此思维导图') {
                    return $this->error($result['message']);
                }
                return $this->error($result['message']);
            }
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage());
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
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
            // 验证思维导图ID
            $this->validator->validateMindmapId($mindmapId);
            
            // 获取当前用户ID（可能为null）
            $userId = $this->getCurrentUserId();
            if ($userId !== null) {
                $this->validator->validateUserId($userId);
            }
            
            $result = $this->nodeLinkService->getMindmapLinks($userId, $mindmapId);
            
            if ($result['success']) {
                return $this->success($result['data'], '获取成功');
            } else {
                if ($result['message'] === '无权限查看此思维导图') {
                    return $this->error($result['message']);
                }
                return $this->error($result['message']);
            }
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage());
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
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
            $userId = $this->getCurrentUserId();
            if (! $userId) {
                return $this->unauthorized('请先登录');
            }
            
            // 验证思维导图ID和链接ID
            $this->validator->validateMindmapId($mindmapId);
            $this->validator->validateLinkId($linkId);
            
            $result = $this->nodeLinkService->deleteNodeLink($userId, $linkId);
            
            if ($result['success']) {
                return $this->success([], '删除成功');
            } else {
                if ($result['message'] === '链接不存在') {
                    return $this->notFound($result['message']);
                }
                if ($result['message'] === '无权限操作此链接') {
                    return $this->forbidden($result['message']);
                }
                return $this->error($result['message'], [], 400);
            }
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage());
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
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
        $userId = $this->request->input('user_id', null);
        return $userId !== null ? (int) $userId : null;
    }
}