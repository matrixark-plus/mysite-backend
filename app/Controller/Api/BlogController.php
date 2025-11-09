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
use App\Middleware\JwtAuthMiddleware;
use App\Service\BlogService;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

/**
 * 博客控制器.
 * @Controller(prefix="/api/blogs")
 */
class BlogController extends AbstractController
{
    /**
     * @Inject
     * @var BlogService
     */
    protected $blogService;

    /**
     * 获取博客列表.
     * @RequestMapping(path="", methods={"GET"})
     */
    public function index(RequestInterface $request)
    {
        try {
            $params = $request->all();
            $blogs = $this->blogService->getBlogs($params);
            return $this->success($blogs, '获取博客列表成功');
        } catch (Exception $e) {
            logger()->error('获取博客列表异常: ' . $e->getMessage());
            return $this->fail(500, '获取博客列表失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取博客详情.
     * @RequestMapping(path="/{id}", methods={"GET"})
     */
    public function show(int $id, RequestInterface $request)
    {
        try {
            // 获取博客详情并自动增加阅读量
            $blog = $this->blogService->getBlogById($id, true);
            if (! $blog) {
                return $this->fail(404, '博客不存在');
            }
            return $this->success($blog, '获取博客详情成功');
        } catch (Exception $e) {
            logger()->error('获取博客详情异常: ' . $e->getMessage());
            return $this->fail(500, '获取博客详情失败: ' . $e->getMessage());
        }
    }

    /**
     * 创建博客.
     * @RequestMapping(path="", methods={"POST"})
     * @Middleware({JwtAuthMiddleware::class})
     */
    public function store(RequestInterface $request)
    {
        try {
            // 检查用户角色，只有管理员可以创建博客
            $userRole = $this->request->getAttribute('user_role') ?? '';
            if ($userRole !== 'admin') {
                return $this->fail(403, '只有管理员可以创建博客');
            }

            $data = $request->all();

            // 验证请求参数
            if (empty($data['title']) || empty($data['content']) || empty($data['category_id'])) {
                return $this->fail(400, '标题、内容和分类不能为空');
            }

            // 获取当前用户ID
            $user = $this->request->getAttribute('user');
            $data['author_id'] = $user->id;
            
            // 添加标签支持
            $data['tags'] = $request->input('tags', []);

            // 创建博客
            $blog = $this->blogService->createBlog($data);

            return $this->success($blog, '创建博客成功', 201);
        } catch (Exception $e) {
            logger()->error('创建博客异常: ' . $e->getMessage());
            return $this->fail(500, '创建博客失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新博客.
     * @RequestMapping(path="/{id}", methods={"PUT"})
     * @Middleware({JwtAuthMiddleware::class})
     */
    public function update(int $id, RequestInterface $request)
    {
        try {
            // 检查用户角色，只有管理员可以更新博客
            $userRole = $this->request->getAttribute('user_role') ?? '';
            if ($userRole !== 'admin') {
                return $this->fail(403, '只有管理员可以更新博客');
            }

            $data = $request->all();
            
            // 特别处理标签字段，确保是数组类型
            if (isset($data['tags']) && !is_array($data['tags'])) {
                $data['tags'] = [];
            }

            // 更新博客
            $blog = $this->blogService->updateBlog($id, $data);

            if (! $blog) {
                return $this->fail(404, '博客不存在');
            }

            return $this->success($blog, '更新博客成功');
        } catch (Exception $e) {
            logger()->error('更新博客异常: ' . $e->getMessage());
            return $this->fail(500, '更新博客失败: ' . $e->getMessage());
        }
    }

    /**
     * 删除博客.
     * @RequestMapping(path="/{id}", methods={"DELETE"})
     * @Middleware({JwtAuthMiddleware::class})
     */
    public function destroy(int $id, RequestInterface $request)
    {
        try {
            // 检查用户角色，只有管理员可以删除博客
            $userRole = $this->request->getAttribute('user_role') ?? '';
            if ($userRole !== 'admin') {
                return $this->fail(403, '只有管理员可以删除博客');
            }

            $result = $this->blogService->deleteBlog($id);

            if (! $result) {
                return $this->fail(404, '博客不存在');
            }

            return $this->success(null, '删除博客成功');
        } catch (Exception $e) {
            logger()->error('删除博客异常: ' . $e->getMessage());
            return $this->fail(500, '删除博客失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取博客分类列表.
     * @RequestMapping(path="/categories", methods={"GET"})
     */
    public function getCategories(RequestInterface $request)
    {
        try {
            $categories = $this->blogService->getCategories();
            return $this->success($categories, '获取博客分类成功');
        } catch (Exception $e) {
            logger()->error('获取博客分类异常: ' . $e->getMessage());
            return $this->fail(500, '获取博客分类失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取热门博客.
     * @RequestMapping(path="/hot", methods={"GET"})
     */
    public function getHotBlogs(RequestInterface $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $blogs = $this->blogService->getHotBlogs($limit);
            return $this->success($blogs, '获取热门博客成功');
        } catch (Exception $e) {
            logger()->error('获取热门博客异常: ' . $e->getMessage());
            return $this->fail(500, '获取热门博客失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取推荐博客.
     * @RequestMapping(path="/recommended", methods={"GET"})
     */
    public function getRecommendedBlogs(RequestInterface $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $blogs = $this->blogService->getRecommendedBlogs($limit);
            return $this->success($blogs, '获取推荐博客成功');
        } catch (Exception $e) {
            logger()->error('获取推荐博客异常: ' . $e->getMessage());
            return $this->fail(500, '获取推荐博客失败: ' . $e->getMessage());
        }
    }

    /**
     * 搜索博客.
     * @RequestMapping(path="/search", methods={"GET"})
     */
    public function searchBlogs(RequestInterface $request)
    {
        try {
            $keyword = $request->input('keyword', '');
            $params = $request->all();

            if (empty($keyword)) {
                return $this->fail(400, '搜索关键词不能为空');
            }

            $blogs = $this->blogService->searchBlogs($keyword, $params);
            return $this->success($blogs, '搜索博客成功');
        } catch (Exception $e) {
            logger()->error('搜索博客异常: ' . $e->getMessage());
            return $this->fail(500, '搜索博客失败: ' . $e->getMessage());
        }
    }
}
