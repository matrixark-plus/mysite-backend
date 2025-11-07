\u003c?php

declare(strict_types=1);

namespace HyperfTest\Controller;

use App\Controller\Api\BlogController;
use App\Service\BlogService;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Logger\LoggerInterface;
use Hyperf\Testing\TestCase;
use Mockery;

/**
 * BlogController的单元测试
 * 测试博客控制器的各项功能
 */
class BlogControllerTest extends TestCase
{
    /**
     * @var BlogController
     */
    protected $controller;

    /**
     * @var Mockery\MockInterface|BlogService
     */
    protected $blogServiceMock;

    /**
     * @var Mockery\MockInterface|LoggerInterface
     */
    protected $loggerMock;

    /**
     * @var Mockery\MockInterface|RequestInterface
     */
    protected $requestMock;

    /**
     * @var Mockery\MockInterface|ResponseInterface
     */
    protected $responseMock;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建模拟对象
        $this-\u003eblogServiceMock = Mockery::mock(BlogService::class);
        $this-\u003eloggerMock = Mockery::mock(LoggerInterface::class);
        $this-\u003erequestMock = Mockery::mock(RequestInterface::class);
        $this-\u003eresponseMock = Mockery::mock(ResponseInterface::class);

        // 获取容器并注册mocks
        $container = ApplicationContext::getContainer();
        $container-\u003eset(BlogService::class, $this-\u003eblogServiceMock);
        $container-\u003eset(LoggerInterface::class, $this-\u003eloggerMock);
        $container-\u003eset(RequestInterface::class, $this-\u003erequestMock);
        $container-\u003eset(ResponseInterface::class, $this-\u003eresponseMock);

        // 直接创建控制器并设置依赖
        $this-\u003econtroller = new BlogController();
        $reflection = new \ReflectionClass($this-\u003econtroller);
        
        // 设置各个属性
        $blogServiceProperty = $reflection-\u003egetProperty('blogService');
        $blogServiceProperty-\u003esetAccessible(true);
        $blogServiceProperty-\u003esetValue($this-\u003econtroller, $this-\u003eblogServiceMock);
        
        $loggerProperty = $reflection-\u003egetProperty('logger');
        $loggerProperty-\u003esetAccessible(true);
        $loggerProperty-\u003esetValue($this-\u003econtroller, $this-\u003eloggerMock);
        
        $requestProperty = $reflection-\u003egetProperty('request');
        $requestProperty-\u003esetAccessible(true);
        $requestProperty-\u003esetValue($this-\u003econtroller, $this-\u003erequestMock);
        
        $responseProperty = $reflection-\u003egetProperty('response');
        $responseProperty-\u003esetAccessible(true);
        $responseProperty-\u003esetValue($this-\u003econtroller, $this-\u003eresponseMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试获取博客列表成功
     */
    public function testIndexSuccess()
    {
        // 准备测试数据
        $params = ['page' => 1, 'page_size' => 10];
        $expectedBlogs = [
            'total' => 20,
            'page' => 1,
            'page_size' => 10,
            'data' => [
                ['id' => 1, 'title' => '测试博客1', 'content' => '内容1'],
                ['id' => 2, 'title' => '测试博客2', 'content' => '内容2']
            ]
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($params);

        // 模拟服务返回
        $this-\u003eblogServiceMock-\u003eshouldReceive('getBlogs')
            -\u003ewith($params)
            -\u003eandReturn($expectedBlogs);

        // 执行测试
        $result = $this-\u003econtroller-\u003eindex($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertArrayHasKey('data', $result);
        $this-\u003eassertEquals($expectedBlogs, $result['data']);
    }

    /**
     * 测试获取博客详情成功
     */
    public function testShowSuccess()
    {
        // 准备测试数据
        $blogId = 1;
        $expectedBlog = [
            'id' => $blogId,
            'title' => '测试博客',
            'content' => '测试内容',
            'category_id' => 1,
            'author_id' => 1
        ];

        // 模拟服务返回
        $this-\u003eblogServiceMock-\u003eshouldReceive('getBlogById')
            -\u003ewith($blogId)
            -\u003eandReturn($expectedBlog);

        // 执行测试
        $result = $this-\u003econtroller-\u003e$show($blogId, $this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertArrayHasKey('data', $result);
        $this-\u003eassertEquals($expectedBlog, $result['data']);
    }

    /**
     * 测试获取博客详情失败-博客不存在
     */
    public function testShowWithNonExistingBlog()
    {
        // 准备测试数据
        $blogId = 999;

        // 模拟服务返回
        $this-\u003eblogServiceMock-\u003eshouldReceive('getBlogById')
            -\u003ewith($blogId)
            -\u003eandReturn(null);

        // 执行测试
        $result = $this-\u003econtroller-\u003e$show($blogId, $this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(404, $result['code']);
        $this-\u003eassertEquals('博客不存在', $result['message']);
    }

    /**
     * 测试创建博客成功（管理员权限）
     */
    public function testStoreSuccess()
    {
        // 准备测试数据
        $data = [
            'title' => '新博客',
            'content' => '新内容',
            'category_id' => 1
        ];
        $expectedBlog = [
            'id' => 1,
            'title' => '新博客',
            'content' => '新内容',
            'category_id' => 1,
            'author_id' => 1
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($data);
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('user_role')-\u003eandReturn('admin');
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('user')-\u003eandReturn(['id' => 1]);

        // 模拟服务返回
        $this-\u003eblogServiceMock-\u003eshouldReceive('createBlog')
            -\u003ewith(array_merge($data, ['author_id' => 1]))
            -\u003eandReturn($expectedBlog);

        // 执行测试
        $result = $this-\u003econtroller-\u003estore($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertArrayHasKey('data', $result);
        $this-\u003eassertEquals($expectedBlog, $result['data']);
    }

    /**
     * 测试创建博客失败-非管理员权限
     */
    public function testStoreWithNonAdminPermission()
    {
        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('user_role')-\u003eandReturn('user');

        // 执行测试
        $result = $this-\u003econtroller-\u003estore($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(403, $result['code']);
        $this-\u003eassertEquals('只有管理员可以创建博客', $result['message']);
    }

    /**
     * 测试创建博客失败-参数不完整
     */
    public function testStoreWithMissingParams()
    {
        // 准备测试数据
        $data = ['title' => '新博客'];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($data);
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('user_role')-\u003eandReturn('admin');

        // 执行测试
        $result = $this-\u003econtroller-\u003estore($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(400, $result['code']);
        $this-\u003eassertEquals('标题、内容和分类不能为空', $result['message']);
    }

    /**
     * 测试更新博客成功（管理员权限）
     */
    public function testUpdateSuccess()
    {
        // 准备测试数据
        $blogId = 1;
        $data = [
            'title' => '更新博客',
            'content' => '更新内容'
        ];
        $expectedBlog = [
            'id' => $blogId,
            'title' => '更新博客',
            'content' => '更新内容',
            'category_id' => 1
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($data);
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('user_role')-\u003eandReturn('admin');

        // 模拟服务返回
        $this-\u003eblogServiceMock-\u003eshouldReceive('updateBlog')
            -\u003ewith($blogId, $data)
            -\u003eandReturn($expectedBlog);

        // 执行测试
        $result = $this-\u003econtroller-\u003eupdate($blogId, $this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertArrayHasKey('data', $result);
        $this-\u003eassertEquals($expectedBlog, $result['data']);
    }

    /**
     * 测试删除博客成功（管理员权限）
     */
    public function testDestroySuccess()
    {
        // 准备测试数据
        $blogId = 1;

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('user_role')-\u003eandReturn('admin');

        // 模拟服务返回
        $this-\u003eblogServiceMock-\u003eshouldReceive('deleteBlog')
            -\u003ewith($blogId)
            -\u003eandReturn(true);

        // 执行测试
        $result = $this-\u003econtroller-\u003edestroy($blogId, $this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertEquals('删除博客成功', $result['message']);
    }

    /**
     * 测试获取博客分类列表
     */
    public function testGetCategories()
    {
        // 准备测试数据
        $expectedCategories = [
            ['id' => 1, 'name' => '分类1'],
            ['id' => 2, 'name' => '分类2']
        ];

        // 模拟服务返回
        $this-\u003eblogServiceMock-\u003eshouldReceive('getCategories')
            -\u003eandReturn($expectedCategories);

        // 执行测试
        $result = $this-\u003econtroller-\u003egetCategories($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertArrayHasKey('data', $result);
        $this-\u003eassertEquals($expectedCategories, $result['data']);
    }

    /**
     * 测试获取热门博客
     */
    public function testGetHotBlogs()
    {
        // 准备测试数据
        $limit = 5;
        $expectedBlogs = [
            ['id' => 1, 'title' => '热门博客1'],
            ['id' => 2, 'title' => '热门博客2']
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('limit', 10)-\u003eandReturn($limit);

        // 模拟服务返回
        $this-\u003eblogServiceMock-\u003eshouldReceive('getHotBlogs')
            -\u003ewith($limit)
            -\u003eandReturn($expectedBlogs);

        // 执行测试
        $result = $this-\u003econtroller-\u003egetHotBlogs($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertArrayHasKey('data', $result);
        $this-\u003eassertEquals($expectedBlogs, $result['data']);
    }

    /**
     * 测试搜索博客成功
     */
    public function testSearchBlogsSuccess()
    {
        // 准备测试数据
        $keyword = '测试';
        $params = ['keyword' => $keyword, 'page' => 1];
        $expectedBlogs = [
            'total' => 2,
            'page' => 1,
            'page_size' => 10,
            'data' => [
                ['id' => 1, 'title' => '测试博客1'],
                ['id' => 2, 'title' => '测试博客2']
            ]
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('keyword', '')-\u003eandReturn($keyword);
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($params);

        // 模拟服务返回
        $this-\u003eblogServiceMock-\u003eshouldReceive('searchBlogs')
            -\u003ewith($keyword, $params)
            -\u003eandReturn($expectedBlogs);

        // 执行测试
        $result = $this-\u003econtroller-\u003esearchBlogs($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertArrayHasKey('data', $result);
        $this-\u003eassertEquals($expectedBlogs, $result['data']);
    }

    /**
     * 测试搜索博客失败-关键词为空
     */
    public function testSearchBlogsWithEmptyKeyword()
    {
        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('input')-\u003ewith('keyword', '')-\u003eandReturn('');

        // 执行测试
        $result = $this-\u003econtroller-\u003esearchBlogs($this-\u003erequestMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(400, $result['code']);
        $this-\u003eassertEquals('搜索关键词不能为空', $result['message']);
    }
}