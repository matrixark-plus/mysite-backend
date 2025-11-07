\u003c?php

declare(strict_types=1);

namespace HyperfTest\Controller;

use App\Controller\Api\CommentController;
use App\Service\CommentService;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Logger\LoggerInterface;
use Hyperf\Testing\TestCase;
use Mockery;

/**
 * CommentController的单元测试
 * 测试评论控制器的各项功能
 */
class CommentControllerTest extends TestCase
{
    /**
     * @var CommentController
     */
    protected $controller;

    /**
     * @var Mockery\MockInterface|CommentService
     */
    protected $commentServiceMock;

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
        $this-\u003ecommentServiceMock = Mockery::mock(CommentService::class);
        $this-\u003eloggerMock = Mockery::mock(LoggerInterface::class);
        $this-\u003erequestMock = Mockery::mock(RequestInterface::class);
        $this-\u003eresponseMock = Mockery::mock(ResponseInterface::class);

        // 获取容器并注册mocks
        $container = ApplicationContext::getContainer();
        $container-\u003eset(CommentService::class, $this-\u003ecommentServiceMock);
        $container-\u003eset(LoggerInterface::class, $this-\u003eloggerMock);
        $container-\u003eset(RequestInterface::class, $this-\u003erequestMock);
        $container-\u003eset(ResponseInterface::class, $this-\u003eresponseMock);

        // 直接创建控制器并设置依赖
        $this-\u003econtroller = new CommentController();
        $reflection = new \ReflectionClass($this-\u003econtroller);
        
        // 设置各个属性
        $commentServiceProperty = $reflection-\u003egetProperty('commentService');
        $commentServiceProperty-\u003esetAccessible(true);
        $commentServiceProperty-\u003esetValue($this-\u003econtroller, $this-\u003ecommentServiceMock);
        
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
     * 测试获取评论列表成功
     */
    public function testIndexSuccess()
    {
        // 准备测试数据
        $params = ['page' => 1, 'page_size' => 10];
        $expectedComments = [
            'total' => 20,
            'page' => 1,
            'page_size' => 10,
            'data' => [
                ['id' => 1, 'content' => '测试评论1'],
                ['id' => 2, 'content' => '测试评论2']
            ]
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($params);

        // 模拟服务返回
        $this-\u003ecommentServiceMock-\u003eshouldReceive('getComments')
            -\u003ewith($params)
            -\u003eandReturn($expectedComments);

        // 执行测试
        $result = $this-\u003econtroller-\u003eindex($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertArrayHasKey('data', $result);
        $this-\u003eassertEquals($expectedComments, $result['data']);
    }

    /**
     * 测试创建评论成功
     */
    public function testStoreSuccess()
    {
        // 准备测试数据
        $data = [
            'post_id' => 1,
            'post_type' => 'blog',
            'content' => '测试评论内容'
        ];
        $userId = 1;
        $commentId = 10;
        $expectedComment = [
            'id' => $commentId,
            'post_id' => 1,
            'post_type' => 'blog',
            'content' => '测试评论内容',
            'user_id' => $userId
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($data);
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('user')-\u003eandReturn(['id' => $userId]);

        // 模拟服务返回
        $this-\u003ecommentServiceMock-\u003eshouldReceive('createComment')
            -\u003ewith(array_merge($data, ['user_id' => $userId]))
            -\u003eandReturn($commentId);
        $this-\u003ecommentServiceMock-\u003eshouldReceive('getCommentById')
            -\u003ewith($commentId)
            -\u003eandReturn($expectedComment);

        // 执行测试
        $result = $this-\u003econtroller-\u003estore($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
        $this-\u003eassertArrayHasKey('data', $result);
        $this-\u003eassertEquals($expectedComment, $result['data']);
    }

    /**
     * 测试创建评论失败-缺少必要参数
     */
    public function testStoreWithMissingParams()
    {
        // 准备测试数据
        $data = ['content' => '缺少post_id和post_type'];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($data);

        // 执行测试
        $result = $this-\u003econtroller-\u003estore($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(400, $result['code']);
    }

    /**
     * 测试创建评论失败-用户未登录
     */
    public function testStoreWithoutLogin()
    {
        // 准备测试数据
        $data = [
            'post_id' => 1,
            'post_type' => 'blog',
            'content' => '测试评论内容'
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($data);
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('user')-\u003eandReturn(null);

        // 执行测试
        $result = $this-\u003econtroller-\u003estore($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(401, $result['code']);
    }

    /**
     * 测试获取评论详情成功-审核通过的评论
     */
    public function testShowSuccess()
    {
        // 准备测试数据
        $commentId = 1;
        $expectedComment = [
            'id' => $commentId,
            'content' => '测试评论',
            'status' => 1 // 已通过审核
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('is_admin')-\u003eandReturn(false);

        // 模拟服务返回
        $this-\u003ecommentServiceMock-\u003eshouldReceive('getCommentById')
            -\u003ewith($commentId)
            -\u003eandReturn($expectedComment);

        // 执行测试
        $result = $this-\u003econtroller-\u003e$show($commentId, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
    }

    /**
     * 测试获取评论详情失败-评论不存在
     */
    public function testShowWithNonExistingComment()
    {
        // 准备测试数据
        $commentId = 999;

        // 模拟服务返回
        $this-\u003ecommentServiceMock-\u003eshouldReceive('getCommentById')
            -\u003ewith($commentId)
            -\u003eandReturn(null);

        // 执行测试
        $result = $this-\u003econtroller-\u003e$show($commentId, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(404, $result['code']);
    }

    /**
     * 测试获取评论详情失败-普通用户查看待审核评论
     */
    public function testShowWithPendingComment()
    {
        // 准备测试数据
        $commentId = 1;
        $comment = [
            'id' => $commentId,
            'content' => '测试评论',
            'status' => 0 // 待审核
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('is_admin')-\u003eandReturn(false);

        // 模拟服务返回
        $this-\u003ecommentServiceMock-\u003eshouldReceive('getCommentById')
            -\u003ewith($commentId)
            -\u003eandReturn($comment);

        // 执行测试
        $result = $this-\u003econtroller-\u003e$show($commentId, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(403, $result['code']);
    }

    /**
     * 测试更新评论成功-评论作者
     */
    public function testUpdateSuccessByAuthor()
    {
        // 准备测试数据
        $commentId = 1;
        $userId = 1;
        $data = ['content' => '更新后的评论内容'];
        $comment = [
            'id' => $commentId,
            'user_id' => $userId
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($data);
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('user')-\u003eandReturn(['id' => $userId]);
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('is_admin')-\u003eandReturn(false);

        // 模拟服务返回
        $this-\u003ecommentServiceMock-\u003eshouldReceive('getCommentById')
            -\u003ewith($commentId)
            -\u003eandReturn($comment);
        $this-\u003ecommentServiceMock-\u003eshouldReceive('updateComment')
            -\u003ewith($commentId, $data)
            -\u003eandReturn(true);

        // 执行测试
        $result = $this-\u003econtroller-\u003eupdate($commentId, $this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
    }

    /**
     * 测试更新评论失败-无权限
     */
    public function testUpdateWithNoPermission()
    {
        // 准备测试数据
        $commentId = 1;
        $userId = 1;
        $comment = [
            'id' => $commentId,
            'user_id' => 2 // 评论属于其他用户
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('user')-\u003eandReturn(['id' => $userId]);
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('is_admin')-\u003eandReturn(false);

        // 模拟服务返回
        $this-\u003ecommentServiceMock-\u003eshouldReceive('getCommentById')
            -\u003ewith($commentId)
            -\u003eandReturn($comment);

        // 执行测试
        $result = $this-\u003econtroller-\u003eupdate($commentId, $this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(403, $result['code']);
    }

    /**
     * 测试删除评论成功-管理员权限
     */
    public function testDestroySuccessByAdmin()
    {
        // 准备测试数据
        $commentId = 1;
        $comment = ['id' => $commentId, 'user_id' => 2];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('is_admin')-\u003eandReturn(true);

        // 模拟服务返回
        $this-\u003ecommentServiceMock-\u003eshouldReceive('getCommentById')
            -\u003ewith($commentId)
            -\u003eandReturn($comment);
        $this-\u003ecommentServiceMock-\u003eshouldReceive('deleteComment')
            -\u003ewith($commentId)
            -\u003eandReturn(true);

        // 执行测试
        $result = $this-\u003econtroller-\u003edestroy($commentId, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
    }

    /**
     * 测试获取待审核评论列表成功-管理员
     */
    public function testGetPendingCommentsSuccess()
    {
        // 准备测试数据
        $params = ['page' => 1];
        $expectedComments = ['total' => 5, 'data' => []];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($params);

        // 模拟服务返回
        $this-\u003ecommentServiceMock-\u003eshouldReceive('getPendingComments')
            -\u003ewith($params)
            -\u003eandReturn($expectedComments);

        // 执行测试
        $result = $this-\u003econtroller-\u003egetPendingComments($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
    }

    /**
     * 测试审核通过评论成功
     */
    public function testApproveCommentSuccess()
    {
        // 准备测试数据
        $commentId = 1;

        // 模拟服务返回
        $this-\u003ecommentServiceMock-\u003eshouldReceive('approveComment')
            -\u003ewith($commentId)
            -\u003eandReturn(true);

        // 执行测试
        $result = $this-\u003econtroller-\u003eapproveComment($commentId, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
    }

    /**
     * 测试拒绝评论成功
     */
    public function testRejectCommentSuccess()
    {
        // 准备测试数据
        $commentId = 1;

        // 模拟服务返回
        $this-\u003ecommentServiceMock-\u003eshouldReceive('rejectComment')
            -\u003ewith($commentId)
            -\u003eandReturn(true);

        // 执行测试
        $result = $this-\u003econtroller-\u003erejectComment($commentId, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
    }

    /**
     * 测试批量审核评论成功
     */
    public function testBatchReviewCommentsSuccess()
    {
        // 准备测试数据
        $data = ['ids' => [1, 2, 3], 'status' => 1]; // 1表示通过
        $expectedResult = ['updated_count' => 3];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($data);

        // 模拟服务返回
        $this-\u003ecommentServiceMock-\u003eshouldReceive('batchReviewComments')
            -\u003ewith([1, 2, 3], 1)
            -\u003eandReturn($expectedResult);

        // 执行测试
        $result = $this-\u003econtroller-\u003ebatchReviewComments($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
    }

    /**
     * 测试批量审核评论失败-参数错误
     */
    public function testBatchReviewCommentsWithInvalidParams()
    {
        // 准备测试数据（缺少ids和status）
        $data = [];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($data);

        // 执行测试
        $result = $this-\u003econtroller-\u003ebatchReviewComments($this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(400, $result['code']);
    }

    /**
     * 测试获取评论回复成功
     */
    public function testGetRepliesSuccess()
    {
        // 准备测试数据
        $commentId = 1;
        $params = [];
        $expectedReplies = ['total' => 3, 'data' => []];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($params);
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('is_admin')-\u003eandReturn(false);

        // 模拟服务返回
        $this-\u003ecommentServiceMock-\u003eshouldReceive('getReplies')
            -\u003ewith($commentId, $params)
            -\u003eandReturn($expectedReplies);

        // 执行测试
        $result = $this-\u003econtroller-\u003 egetReplies($commentId, $this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
    }

    /**
     * 测试回复评论成功
     */
    public function testReplyCommentSuccess()
    {
        // 准备测试数据
        $commentId = 1;
        $data = ['content' => '回复内容'];
        $userId = 1;
        $replyId = 10;
        $expectedReply = [
            'id' => $replyId,
            'content' => '回复内容',
            'user_id' => $userId,
            'parent_id' => $commentId
        ];

        // 模拟请求参数
        $this-\u003erequestMock-\u003eshouldReceive('all')-\u003eandReturn($data);
        $this-\u003erequestMock-\u003eshouldReceive('getAttribute')-\u003ewith('user')-\u003eandReturn(['id' => $userId]);

        // 模拟服务返回
        $this-\u003ecommentServiceMock-\u003eshouldReceive('replyComment')
            -\u003ewith($commentId, array_merge($data, ['user_id' => $userId]))
            -\u003eandReturn($replyId);
        $this-\u003ecommentServiceMock-\u003eshouldReceive('getCommentById')
            -\u003ewith($replyId)
            -\u003eandReturn($expectedReply);

        // 执行测试
        $result = $this-\u003econtroller-\u003ereplyComment($commentId, $this-\u003erequestMock, $this-\u003eresponseMock);

        // 验证结果
        $this-\u003eassertIsArray($result);
        $this-\u003eassertArrayHasKey('code', $result);
        $this-\u003eassertEquals(0, $result['code']);
    }
}