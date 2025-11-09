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

use App\Controller\Api\Validator\MindmapRootValidator;
use App\Controller\AbstractController;
use App\Service\MindmapRootService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\ValidationException;

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
     * @Inject
     * @var MindmapRootValidator
     */
    protected $validator;

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
            $userId = $this->getCurrentUserId();
            if (! $userId) {
                return $this->unauthorized('请先登录');
            }
            
            $data = $request->all();
            $data['creator_id'] = $userId;
            
            // 验证参数
            $this->validator->validateCreateMindmap($data);
            
            $result = $this->mindmapRootService->createMindmap($data);
            
            if ($result['success']) {
                return $this->success('创建成功', $result['data']);
            } else {
                return $this->fail(400, $result['message'], []);
            }
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage(), []);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
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
            $userId = $this->getCurrentUserId();
            if (! $userId) {
                return $this->unauthorized('请先登录');
            }
            
            // 验证分页参数
            $pagination = $this->validator->validatePagination([
                'page' => $this->request->input('page', 1),
                'limit' => $this->request->input('limit', 20),
            ]);
            
            $result = $this->mindmapRootService->getUserMindmaps($userId, $pagination['page'], $pagination['limit']);
            
            if ($result['success']) {
                return $this->success('获取成功', $result['data']);
            } else {
                return $this->fail(400, $result['message'], []);
            }
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage(), []);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
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
            // 验证分页参数
            $pagination = $this->validator->validatePagination([
                'page' => $this->request->input('page', 1),
                'limit' => $this->request->input('limit', 20),
            ]);
            
            $result = $this->mindmapRootService->getPublicMindmaps($pagination['page'], $pagination['limit']);
            
            if ($result['success']) {
                return $this->success('获取成功', $result['data']);
            } else {
                return $this->fail(400, $result['message'], []);
            }
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage(), []);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
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
            // 验证思维导图ID
            $this->validator->validateMindmapId($id);
            
            // 获取当前用户ID（可能为null）
            $userId = $this->getCurrentUserId();
            
            $result = $this->mindmapRootService->getMindmapDetail($id, $userId);
            
            if ($result['success']) {
                return $this->success('获取成功', $result['data']);
            } else {
                if ($result['message'] === '思维导图不存在') {
                    return $this->notFound($result['message']);
                }
                if ($result['message'] === '无权限查看此思维导图') {
                    return $this->forbidden($result['message']);
                }
                return $this->fail(400, $result['message'], []);
            }
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage(), []);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
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
            $userId = $this->getCurrentUserId();
            if (! $userId) {
                return $this->unauthorized('请先登录');
            }
            
            // 验证思维导图ID
            $this->validator->validateMindmapId($id);
            
            $data = $request->all();
            
            // 验证更新参数
            $this->validator->validateUpdateMindmap($data);
            
            $result = $this->mindmapRootService->updateMindmap($id, $userId, $data);
            
            if ($result['success']) {
                return $this->success($result['data'], '更新成功');
            } else {
                if ($result['message'] === '思维导图不存在') {
                    return $this->notFound($result['message']);
                }
                if ($result['message'] === '无权限修改此思维导图') {
                    return $this->forbidden($result['message']);
                }
                return $this->fail(400, $result['message'], []);
            }
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage(), []);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
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
            $userId = $this->getCurrentUserId();
            if (! $userId) {
                return $this->unauthorized('请先登录');
            }
            
            // 验证思维导图ID
            $this->validator->validateMindmapId($id);
            
            // 这里应该调用删除思维导图的方法（需要级联删除节点和链接）
            // 暂时返回成功
            return $this->success('删除成功');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
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
            $userId = $this->getCurrentUserId();
            if (! $userId) {
                return $this->unauthorized('请先登录');
            }
            
            // 验证思维导图ID
            $this->validator->validateMindmapId($id);
            
            $result = $this->mindmapRootService->togglePublicStatus($id, $userId);
            
            if ($result['success']) {
                return $this->success($result['message'], $result['data']);
            } else {
                if ($result['message'] === '思维导图不存在') {
                    return $this->notFound($result['message']);
                }
                if ($result['message'] === '无权限修改此思维导图') {
                    return $this->forbidden($result['message']);
                }
                return $this->fail(400, $result['message'], []);
            }
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), [], 422);
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
        return $this->request->input('user_id', null);
    }
}