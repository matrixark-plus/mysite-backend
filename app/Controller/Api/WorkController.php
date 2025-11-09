<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Hyperf\Context\Context;

use App\Constants\StatusCode;
use App\Controller\AbstractController;
use App\Controller\Api\Validator\WorkValidator;
use App\Service\WorkService;
use App\Traits\LogTrait;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use App\Middleware\JwtAuthMiddleware;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Validation\ValidationException;

/**
 * 作品控制器
 * @Controller(prefix="/api/works")
 */
class WorkController extends AbstractController
{
    use LogTrait;
    
    /**
     * @var WorkService
     * @Inject
     */
    protected $workService;

    /**
     * @var WorkValidator
     * @Inject
     */
    protected $validator;

    /**
     * 获取作品列表
     * @RequestMapping(path="", methods={"GET"})
     */
    public function index()
    {
        try {
            $params = $this->request->all();
            $userId = Context::has('user_id') ? Context::get('user_id') : null;
            
            // 验证参数
            try {
                $validatedData = $this->validator->validateWorkList($params);
            } catch (ValidationException $e) {
                return $this->validationError('参数验证失败', $e->validator->errors());
            }

            // 设置默认值
            $params['page'] = $params['page'] ?? 1;
            $params['per_page'] = $params['per_page'] ?? 10;
            
            $result = $this->workService->getWorks($params, $userId);
            
            return $this->success($result, '获取成功');
        } catch (\Exception $e) {
            $this->logError('获取作品列表失败', ['error' => $e->getMessage()], $e, 'work');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取作品列表失败');
        }
    }

    /**
     * 获取作品详情
     * @RequestMapping(path="/{id}", methods={"GET"})
     */
    public function show($id)
    {
        try {
            $userId = Context::has('user_id') ? Context::get('user_id') : null;
            
            $work = $this->workService->getWorkById($id, $userId);
            
            if (!$work) {
                return $this->notFound('作品不存在');
            }
            
            return $this->success($work, '获取成功');
        } catch (\Exception $e) {
            $this->logError('获取作品详情失败', ['error' => $e->getMessage()], $e, 'work');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取作品详情失败');
        }
    }

    /**
     * 创建作品
     * @RequestMapping(path="", methods={"POST"})
     * @Middleware({JwtAuthMiddleware::class})
     */
    public function store()
    {
        try {
            $params = $this->request->all();
            $userId = (int) Context::get('user_id');
            
            // 验证参数
            try {
                $validatedData = $this->validator->validateCreateWork($params);
            } catch (ValidationException $e) {
                return $this->validationError('参数验证失败', $e->validator->errors());
            }
            
            // 处理图片数组
            if (isset($params['images']) && is_array($params['images'])) {
                $params['images'] = json_encode($params['images'], JSON_UNESCAPED_UNICODE);
            }
            
            $work = $this->workService->createWork($userId, $params);
            
            return $this->success($work, '创建成功', StatusCode::CREATED);
        } catch (\Exception $e) {
            $this->logError('创建作品失败', ['error' => $e->getMessage()], $e, 'work');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '创建作品失败');
        }
    }

    /**
     * 更新作品
     * @RequestMapping(path="/{id}", methods={"PUT"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function update($id)
    {
        try {
            $params = $this->request->all();
            $userId = (int) Context::get('user_id');
            
            // 验证参数
            try {
                $validatedData = $this->validator->validateUpdateWork($params);
            } catch (ValidationException $e) {
                return $this->validationError('参数验证失败', $e->validator->errors());
            }
            
            // 处理图片数组
            if (isset($params['images']) && is_array($params['images'])) {
                $params['images'] = json_encode($params['images'], JSON_UNESCAPED_UNICODE);
            }
            
            // 检查作品是否存在且属于当前用户
            $existingWork = $this->workService->getWorkById($id, $userId);
            if (!$existingWork) {
                return $this->notFound('作品不存在或无权限操作');
            }
            
            $work = $this->workService->updateWork($id, $userId, $params);
            
            return $this->success($work, '更新成功');
        } catch (\Exception $e) {
            $this->logError('更新作品失败', ['error' => $e->getMessage()], $e, 'work');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '更新作品失败');
        }
    }

    /**
     * 删除作品
     * @RequestMapping(path="/{id}", methods={"DELETE"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function destroy($id)
    {
        try {
            $userId = (int) Context::get('user_id');
            
            // 检查作品是否存在且属于当前用户
            $existingWork = $this->workService->getWorkById($id, $userId);
            if (!$existingWork) {
                return $this->notFound('作品不存在或无权限操作');
            }
            
            $this->workService->deleteWork($id, $userId);
            
            return $this->success([], '删除成功');
        } catch (\Exception $e) {
            $this->logError('删除作品失败', ['error' => $e->getMessage()], $e, 'work');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '删除作品失败');
        }
    }

    /**
     * 获取作品分类列表
     * @RequestMapping(path="/categories", methods={"GET"})
     */
    public function getCategories()
    {
        try {
            $categories = $this->workService->getCategories();
            
            return $this->success($categories, '获取成功');
        } catch (\Exception $e) {
            $this->logError('获取分类列表失败', ['error' => $e->getMessage()], $e, 'work');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取分类列表失败');
        }
    }

    /**
     * 创建作品分类
     * @RequestMapping(path="/categories", methods={"POST"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function createCategory()
    {
        try {
            $params = $this->request->all();
            $userId = Context::get('user_id');
            
            // 验证参数
            try {
                $validatedData = $this->validator->validateCreateCategory($params);
            } catch (ValidationException $e) {
                return $this->validationError('参数验证失败', $e->validator->errors());
            }
            
            $category = $this->workService->createCategory($params);
            
            return $this->success($category, '创建成功', StatusCode::CREATED);
        } catch (\Exception $e) {
            $this->logError('创建分类失败', ['error' => $e->getMessage()], $e, 'work');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '创建分类失败');
        }
    }

    /**
     * 更新作品分类
     * @RequestMapping(path="/categories/{id}", methods={"PUT"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function updateCategory($id)
    {
        try {
            $params = $this->request->all();
            
            // 验证参数
            try {
                $validatedData = $this->validator->validateUpdateCategory($params, (int)$id);
            } catch (ValidationException $e) {
                return $this->validationError('参数验证失败', $e->validator->errors());
            }
            
            $category = $this->workService->updateCategory($id, $params);
            
            if (!$category) {
                return $this->notFound('分类不存在');
            }
            
            return $this->success($category, '更新成功');
        } catch (\Exception $e) {
            $this->logError('更新分类失败', ['error' => $e->getMessage()], $e, 'work');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '更新分类失败');
        }
    }

    /**
     * 删除作品分类
     * @RequestMapping(path="/categories/{id}", methods={"DELETE"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function deleteCategory($id)
    {
        try {
            // 检查分类是否被使用
            $workCount = $this->workService->getWorkCountByCategory($id);
            if ($workCount > 0) {
                return $this->fail(StatusCode::BAD_REQUEST, '该分类下还有作品，无法删除');
            }
            
            $result = $this->workService->deleteCategory($id);
            
            if (!$result) {
                return $this->notFound('分类不存在');
            }
            
            return $this->success(null, '删除成功');
        } catch (\Exception $e) {
            $this->logError('删除分类失败', ['error' => $e->getMessage()], $e, 'work');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '删除分类失败');
        }
    }
}