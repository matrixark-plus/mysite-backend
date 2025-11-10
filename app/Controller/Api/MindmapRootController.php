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
use App\Controller\Api\Validator\MindmapRootValidator;
use App\Service\MindmapRootService;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\ValidationException;

/**
 * 思维导图根节点控制器
 * 处理思维导图根节点相关的HTTP请求.
 * @Controller(prefix="/api/mindmaps")
 */
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
     * 创建思维导图.
     * @return array
     * @RequestMapping(path="/", methods={"POST"})
     */
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
                return $this->success($result['data'], '创建成功');
            }
            return $this->fail(400, $result['message'], []);
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage(), []);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * 获取用户的思维导图列表.
     * @return array
     * @RequestMapping(path="/", methods={"GET"})
     */
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
                return $this->success($result['data'], '获取成功');
            }
            return $this->fail(400, $result['message'], []);
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage(), []);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * 获取公开的思维导图列表.
     * @return array
     * @RequestMapping(path="/public", methods={"GET"})
     */
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
                return $this->success($result['data'], '获取成功');
            }
            return $this->fail(400, $result['message'], []);
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage(), []);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * 获取思维导图详情.
     * @param int $id 思维导图ID
     * @return array
     * @RequestMapping(path="/{id}", methods={"GET"})
     */
    public function detail(int $id)
    {
        try {
            // 验证思维导图ID
            $this->validator->validateMindmapId($id);

            // 获取当前用户ID（可能为null）
            $userId = $this->getCurrentUserId();

            $result = $this->mindmapRootService->getMindmapDetail($id, $userId);

            if ($result['success']) {
                return $this->success($result['data'], '获取成功');
            }
            if ($result['message'] === '思维导图不存在') {
                return $this->notFound($result['message']);
            }
            if ($result['message'] === '无权限查看此思维导图') {
                return $this->forbidden($result['message']);
            }
            return $this->fail(400, $result['message'], []);
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage(), []);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * 更新思维导图.
     * @param int $id 思维导图ID
     * @return array
     * @RequestMapping(path="/{id}", methods={"PUT"})
     */
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

            $result = $this->mindmapRootService->updateMindmap($id, $data, $userId);

            if ($result['success']) {
                return $this->success($result['data'], '更新成功');
            }
            if ($result['message'] === '思维导图不存在') {
                return $this->notFound($result['message']);
            }
            if ($result['message'] === '无权限修改此思维导图') {
                return $this->forbidden($result['message']);
            }
            return $this->fail(400, $result['message'], []);
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage(), []);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * 删除思维导图.
     * @param int $id 思维导图ID
     * @return array
     * @RequestMapping(path="/{id}", methods={"DELETE"})
     */
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

            // 调用服务层删除思维导图（级联删除节点和链接）
            $result = $this->mindmapRootService->deleteMindmap($id, $userId);

            if ($result['success']) {
                return $this->success([], '删除成功');
            }
            if ($result['message'] === '思维导图不存在') {
                return $this->notFound($result['message']);
            }
            if ($result['message'] === '无权限修改此思维导图') {
                return $this->forbidden($result['message']);
            }
            return $this->fail(400, $result['message'], []);
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage(), []);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * 切换思维导图的公开状态
     * @param int $id 思维导图ID
     * @return array
     * @RequestMapping(path="/{id}/toggle-public", methods={"PUT"})
     */
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
                return $this->success($result['data'], $result['message']);
            }
            if ($result['message'] === '思维导图不存在') {
                return $this->notFound($result['message']);
            }
            if ($result['message'] === '无权限修改此思维导图') {
                return $this->forbidden($result['message']);
            }
            return $this->fail(400, $result['message'], []);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), [], 422);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * 获取当前登录用户的ID
     * 实际项目中应该从JWT token或session中获取.
     *
     * @return null|int 用户ID
     */
    protected function getCurrentUserId(): ?int
    {
        // 这里只是模拟，实际项目中应该实现真实的用户认证逻辑
        $userId = $this->request->input('user_id', null);
        return $userId !== null ? (int) $userId : null;
    }
}
