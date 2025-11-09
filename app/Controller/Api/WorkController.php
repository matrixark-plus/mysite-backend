<?php

namespace App\Controller\Api;

use App\Service\WorkService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Utils\Context;
use App\Middleware\JwtAuthMiddleware;
use Hyperf\HttpServer\Annotation\Middleware;

/**
 * 作品控制器
 * @Controller(prefix="/api/works")
 */
class WorkController
{
    /**
     * @Inject
     * @var WorkService
     */
    protected $workService;

    /**
     * @Inject
     * @var ValidatorFactoryInterface
     */
    protected $validatorFactory;

    /**
     * 获取作品列表
     * @RequestMapping(path="", methods={"GET"})
     */
    public function index(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $params = $request->all();
            $userId = Context::has('user_id') ? Context::get('user_id') : null;
            
            // 验证参数
            $validator = $this->validatorFactory->make($params, [
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'category_id' => 'sometimes|integer|min:1',
                'keyword' => 'sometimes|string|max:255',
            ]);

            if ($validator->fails()) {
                return $response->json([
                    'code' => 400,
                    'message' => '参数验证失败',
                    'data' => $validator->errors()->toArray(),
                ]);
            }

            // 设置默认值
            $params['page'] = $params['page'] ?? 1;
            $params['per_page'] = $params['per_page'] ?? 10;
            
            $result = $this->workService->getWorks($params, $userId);
            
            return $response->json([
                'code' => 200,
                'message' => '获取成功',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '获取作品列表失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * 获取作品详情
     * @RequestMapping(path="/{id}", methods={"GET"})
     */
    public function show($id, ResponseInterface $response)
    {
        try {
            $userId = Context::has('user_id') ? Context::get('user_id') : null;
            
            $work = $this->workService->getWorkById($id, $userId);
            
            if (!$work) {
                return $response->json([
                    'code' => 404,
                    'message' => '作品不存在',
                    'data' => [],
                ]);
            }
            
            return $response->json([
                'code' => 200,
                'message' => '获取成功',
                'data' => $work,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '获取作品详情失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * 创建作品
     * @RequestMapping(path="", methods={"POST"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function store(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $params = $request->all();
            $userId = Context::get('user_id');
            
            // 验证参数
            $validator = $this->validatorFactory->make($params, [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'category_id' => 'required|integer|min:1',
                'cover_image' => 'sometimes|string|max:500',
                'images' => 'sometimes|array',
                'demo_url' => 'sometimes|url|max:500',
                'source_url' => 'sometimes|url|max:500',
                'is_public' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return $response->json([
                    'code' => 400,
                    'message' => '参数验证失败',
                    'data' => $validator->errors()->toArray(),
                ]);
            }
            
            // 处理图片数组
            if (isset($params['images']) && is_array($params['images'])) {
                $params['images'] = json_encode($params['images'], JSON_UNESCAPED_UNICODE);
            }
            
            $work = $this->workService->createWork($userId, $params);
            
            return $response->json([
                'code' => 201,
                'message' => '创建成功',
                'data' => $work,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '创建作品失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * 更新作品
     * @RequestMapping(path="/{id}", methods={"PUT"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function update($id, RequestInterface $request, ResponseInterface $response)
    {
        try {
            $params = $request->all();
            $userId = Context::get('user_id');
            
            // 验证参数
            $validator = $this->validatorFactory->make($params, [
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'category_id' => 'sometimes|integer|min:1',
                'cover_image' => 'sometimes|string|max:500',
                'images' => 'sometimes|array',
                'demo_url' => 'sometimes|url|max:500',
                'source_url' => 'sometimes|url|max:500',
                'is_public' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return $response->json([
                    'code' => 400,
                    'message' => '参数验证失败',
                    'data' => $validator->errors()->toArray(),
                ]);
            }
            
            // 处理图片数组
            if (isset($params['images']) && is_array($params['images'])) {
                $params['images'] = json_encode($params['images'], JSON_UNESCAPED_UNICODE);
            }
            
            // 检查作品是否存在且属于当前用户
            $existingWork = $this->workService->getWorkById($id, $userId);
            if (!$existingWork) {
                return $response->json([
                    'code' => 404,
                    'message' => '作品不存在或无权限操作',
                    'data' => [],
                ]);
            }
            
            $work = $this->workService->updateWork($id, $userId, $params);
            
            return $response->json([
                'code' => 200,
                'message' => '更新成功',
                'data' => $work,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '更新作品失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * 删除作品
     * @RequestMapping(path="/{id}", methods={"DELETE"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function destroy($id, ResponseInterface $response)
    {
        try {
            $userId = Context::get('user_id');
            
            // 检查作品是否存在且属于当前用户
            $existingWork = $this->workService->getWorkById($id, $userId);
            if (!$existingWork) {
                return $response->json([
                    'code' => 404,
                    'message' => '作品不存在或无权限操作',
                    'data' => [],
                ]);
            }
            
            $this->workService->deleteWork($id, $userId);
            
            return $response->json([
                'code' => 200,
                'message' => '删除成功',
                'data' => [],
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '删除作品失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * 获取作品分类列表
     * @RequestMapping(path="/categories", methods={"GET"})
     */
    public function getCategories(ResponseInterface $response)
    {
        try {
            $categories = $this->workService->getCategories();
            
            return $response->json([
                'code' => 200,
                'message' => '获取成功',
                'data' => $categories,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '获取分类列表失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * 创建作品分类
     * @RequestMapping(path="/categories", methods={"POST"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function createCategory(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $params = $request->all();
            $userId = Context::get('user_id');
            
            // 验证参数
            $validator = $this->validatorFactory->make($params, [
                'name' => 'required|string|max:100|unique:work_categories,name',
                'description' => 'sometimes|string|max:500',
                'sort_order' => 'sometimes|integer|min:0',
            ]);

            if ($validator->fails()) {
                return $response->json([
                    'code' => 400,
                    'message' => '参数验证失败',
                    'data' => $validator->errors()->toArray(),
                ]);
            }
            
            $category = $this->workService->createCategory($params);
            
            return $response->json([
                'code' => 201,
                'message' => '创建成功',
                'data' => $category,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '创建分类失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * 更新作品分类
     * @RequestMapping(path="/categories/{id}", methods={"PUT"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function updateCategory($id, RequestInterface $request, ResponseInterface $response)
    {
        try {
            $params = $request->all();
            
            // 验证参数
            $validator = $this->validatorFactory->make($params, [
                'name' => 'sometimes|string|max:100|unique:work_categories,name,' . $id,
                'description' => 'sometimes|string|max:500',
                'sort_order' => 'sometimes|integer|min:0',
            ]);

            if ($validator->fails()) {
                return $response->json([
                    'code' => 400,
                    'message' => '参数验证失败',
                    'data' => $validator->errors()->toArray(),
                ]);
            }
            
            $category = $this->workService->updateCategory($id, $params);
            
            if (!$category) {
                return $response->json([
                    'code' => 404,
                    'message' => '分类不存在',
                    'data' => [],
                ]);
            }
            
            return $response->json([
                'code' => 200,
                'message' => '更新成功',
                'data' => $category,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '更新分类失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * 删除作品分类
     * @RequestMapping(path="/categories/{id}", methods={"DELETE"})
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function deleteCategory($id, ResponseInterface $response)
    {
        try {
            // 检查分类是否被使用
            $workCount = $this->workService->getWorkCountByCategory($id);
            if ($workCount > 0) {
                return $response->json([
                    'code' => 400,
                    'message' => '该分类下还有作品，无法删除',
                    'data' => [],
                ]);
            }
            
            $result = $this->workService->deleteCategory($id);
            
            if (!$result) {
                return $response->json([
                    'code' => 404,
                    'message' => '分类不存在',
                    'data' => [],
                ]);
            }
            
            return $response->json([
                'code' => 200,
                'message' => '删除成功',
                'data' => [],
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '删除分类失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }
}