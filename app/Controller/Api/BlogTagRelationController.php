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

use App\Controller\Api\Validator\BlogTagRelationValidator;
use App\Service\BlogTagRelationService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\ValidationException;

/**
 * 博客标签关系控制器
 * 处理博客与标签之间的关联相关的HTTP请求.
 * @Controller(prefix="/api/blogs")
 */
class BlogTagRelationController extends AbstractController
{
    /**
     * @Inject
     * @var BlogTagRelationService
     */
    protected $blogTagRelationService;
    
    /**
     * @Inject
     * @var BlogTagRelationValidator
     */
    protected $blogTagRelationValidator;

    /**
     * 为博客添加标签
     *
     * @param int $blogId 博客ID
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/{blogId}/tags', methods: ['POST'])]
    public function addTags(int $blogId, RequestInterface $request)
    {
        try {
            // 权限验证 - 这里应该验证当前用户是否有权限编辑该博客
            // $this-\u003echeckBlogPermission($blogId);
            
            $data = $request-\u003eall();
            // 验证参数
            $validatedData = $this-\u003eblogTagRelationValidator-\u003evalidateAddTags($data);
            $tagIds = $validatedData['tag_ids'];
            // 验证博客ID
            $this-\u003eblogTagRelationValidator-\u003evalidateBlogId($blogId);
            
            $result = $this-\u003eblogTagRelationService-\u003eaddTagsToBlog($blogId, $tagIds);
            
            if ($result['success']) {
                return $this-\u003esuccess($result['message'], $result['data'] ?? []);
            } else {
                return $this-\u003eerror($result['message'], [], 400);
            }
        } catch (ValidationException $e) {
            return $this-\u003eerror('参数验证失败', $e-\u003evalidator-\u003eerrors()-\u003eall(), 400);
        } catch (\Exception $e) {
            return $this-\u003eserverError($e-\u003egetMessage());
        }
    }

    /**
     * 更新博客的标签
     * 会先删除原有的关联，然后添加新的关联
     *
     * @param int $blogId 博客ID
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/{blogId}/tags', methods: ['PUT'])]
    public function updateTags(int $blogId, RequestInterface $request)
    {
        try {
            // 权限验证
            // $this-\u003echeckBlogPermission($blogId);
            
            $data = $request-\u003eall();
            // 验证参数
            $validatedData = $this-\u003eblogTagRelationValidator-\u003evalidateUpdateTags($data);
            $tagIds = $validatedData['tag_ids'];
            // 验证博客ID
            $this-\u003eblogTagRelationValidator-\u003evalidateBlogId($blogId);
            
            $result = $this-\u003eblogTagRelationService-\u003eupdateBlogTags($blogId, $tagIds);
            
            if ($result['success']) {
                return $this-\u003esuccess($result['message'], $result['data'] ?? []);
            } else {
                return $this-\u003eerror($result['message'], [], 400);
            }
        } catch (\Exception $e) {
            return $this-\u003eserverError($e-\u003egetMessage());
        }
    }

    /**
     * 从博客中移除标签
     *
     * @param int $blogId 博客ID
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    #[RequestMapping(path: '/{blogId}/tags', methods: ['DELETE'])]
    public function removeTags(int $blogId, RequestInterface $request)
    {
        try {
            // 权限验证
            // $this-\u003echeckBlogPermission($blogId);
            
            $data = $request-\u003eall();
            // 验证参数
            $validatedData = $this-\u003eblogTagRelationValidator-\u003evalidateRemoveTags($data);
            $tagIds = $validatedData['tag_ids'];
            // 验证博客ID
            $this-\u003eblogTagRelationValidator-\u003evalidateBlogId($blogId);
            
            $result = $this-\u003eblogTagRelationService-\u003eremoveTagsFromBlog($blogId, $tagIds);
            
            if ($result['success']) {
                return $this-\u003esuccess($result['message']);
            } else {
                return $this-\u003eerror($result['message'], [], 400);
            }
        } catch (\Exception $e) {
            return $this-\u003eserverError($e-\u003egetMessage());
        }
    }

    /**
     * 获取博客的标签ID列表
     *
     * @param int $blogId 博客ID
     * @return ResponseInterface
     * @RequestMapping(path="/{blogId}/tags", methods={"GET"})
     */
    public function getBlogTags(int $blogId)
    {
        try {
            // 验证博客ID
            $this-\u003eblogTagRelationValidator-\u003evalidateBlogId($blogId);
            $tagIds = $this-\u003eblogTagRelationService-\u003 egetBlogTagIds($blogId);
            
            return $this-\u003esuccess('获取成功', [
                'blog_id' =\u003e $blogId,
                'tag_ids' =\u003e $tagIds,
                'count' =\u003e count($tagIds),
            ]);
        } catch (\Exception $e) {
            return $this-\u003eserverError($e-\u003egetMessage());
        }
    }

    /**
     * 获取标签下的博客ID列表
     *
     * @param int $tagId 标签ID
     * @return ResponseInterface
     * @RequestMapping(path="/tags/{tagId}/blogs", methods={"GET"})
     */
    public function getTagBlogs(int $tagId)
    {
        try {
            // 验证标签ID
            $this-\u003eblogTagRelationValidator-\u003evalidateTagId($tagId);
            $blogIds = $this-\u003eblogTagRelationService-\u003 egetTagBlogIds($tagId);
            
            return $this-\u003esuccess('获取成功', [
                'tag_id' =\u003e $tagId,
                'blog_ids' =\u003e $blogIds,
                'count' =\u003e count($blogIds),
            ]);
        } catch (\Exception $e) {
            return $this-\u003eserverError($e-\u003egetMessage());
        }
    }
}