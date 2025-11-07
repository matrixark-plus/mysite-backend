\u003c?php

declare(strict_types=1);

namespace HyperfTest\Service;

use App\Model\User;
use App\Service\UserService;
use Hyperf\Context\ApplicationContext;
use Hyperf\DbConnection\Db;
use Hyperf\Logger\LoggerInterface;
use Hyperf\Testing\TestCase;
use Mockery;

/**
 * UserService的单元测试
 * 测试用户服务的各项功能
 */
class UserServiceTest extends TestCase
{
    /**
     * @var UserService
     */
    protected $service;

    /**
     * @var Mockery\MockInterface|User
     */
    protected $userModelMock;

    /**
     * @var Mockery\MockInterface|Db
     */
    protected $dbMock;

    /**
     * @var Mockery\MockInterface|LoggerInterface
     */
    protected $loggerMock;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建mocks
        $this-\u003euserModelMock = Mockery::mock(User::class);
        $this-\u003edbMock = Mockery::mock(Db::class . ':[transaction]');
        $this-\u003eloggerMock = Mockery::mock(LoggerInterface::class);

        // 获取容器并注册mocks
        $container = ApplicationContext::getContainer();
        $container-\u003eset(LoggerInterface::class, $this-\u003eloggerMock);

        // 创建服务实例
        $this-\u003eservice = new UserService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试创建用户成功的情况
     */
    public function testCreateUserSuccess()
    {
        // 准备测试数据
        $data = [
            'username' =\u003e 'testuser',
            'email' =\u003e 'test@example.com',
            'password' =\u003e 'password123'
        ];

        // 模拟User模型行为
        User::shouldReceive('query')-\u003eandReturnSelf();
        User::shouldReceive('where')-\u003eandReturnSelf();
        User::shouldReceive('first')-\u003etwice()-\u003eandReturn(null);

        // 创建一个真实的User实例用于测试
        $userMock = new class extends User {
            public function save() { return true; }
            public function setPasswordAttribute($value) { $this-\u003epassword_hash = password_hash($value, PASSWORD_DEFAULT); }
        };

        // 模拟构造函数行为
        $userClassMock = Mockery::mock('alias:App\\Model\\User');
        $userClassMock-\u003eshouldReceive('__construct')-\u003eandReturn($userMock);

        // 模拟事务行为
        $this-\u003edbMock-\u003eshouldReceive('transaction')-\u003eandReturnUsing(function ($callback) {
            return $callback();
        });

        // 执行测试
        $result = $this-\u003eservice-\u003ecreateUser($data);

        // 验证结果
        $this-\u003eassertInstanceOf(User::class, $result);
        $this-\u003eassertEquals($data['username'], $result-\u003eusername);
        $this-\u003eassertEquals($data['email'], $result-\u003eemail);
    }

    /**
     * 测试创建用户失败-用户名已存在
     */
    public function testCreateUserWithExistingUsername()
    {
        // 准备测试数据
        $data = [
            'username' =\u003e 'existinguser',
            'email' =\u003e 'test@example.com',
            'password' =\u003e 'password123'
        ];

        // 模拟User模型行为
        User::shouldReceive('query')-\u003eandReturnSelf();
        User::shouldReceive('where')-\u003ewith('username', $data['username'])-\u003eandReturnSelf();
        User::shouldReceive('first')-\u003eonce()-\u003eandReturn(new User());

        // 执行测试并验证异常
        $this-\u003eexpectException(\InvalidArgumentException::class);
        $this-\u003eexpectExceptionMessage('用户名已存在');
        $this-\u003eservice-\u003ecreateUser($data);
    }

    /**
     * 测试创建用户失败-邮箱已存在
     */
    public function testCreateUserWithExistingEmail()
    {
        // 准备测试数据
        $data = [
            'username' =\u003e 'testuser',
            'email' =\u003e 'existing@example.com',
            'password' =\u003e 'password123'
        ];

        // 模拟User模型行为
        User::shouldReceive('query')-\u003eandReturnSelf();
        User::shouldReceive('where')-\u003ewith('username', $data['username'])-\u003eandReturnSelf();
        User::shouldReceive('first')-\u003eonce()-\u003eandReturn(null);
        User::shouldReceive('where')-\u003ewith('email', $data['email'])-\u003eandReturnSelf();
        User::shouldReceive('first')-\u003eonce()-\u003eandReturn(new User());

        // 执行测试并验证异常
        $this-\u003eexpectException(\InvalidArgumentException::class);
        $this-\u003eexpectExceptionMessage('邮箱已被注册');
        $this-\u003eservice-\u003ecreateUser($data);
    }

    /**
     * 测试根据ID获取用户
     */
    public function testGetUserById()
    {
        // 准备测试数据
        $userId = 1;
        $expectedUser = new User();
        $expectedUser-\u003eid = $userId;

        // 模拟User模型行为
        User::shouldReceive('find')-\u003ewith($userId)-\u003eandReturn($expectedUser);

        // 执行测试
        $result = $this-\u003eservice-\u003 egetUserById($userId);

        // 验证结果
        $this-\u003eassertInstanceOf(User::class, $result);
        $this-\u003eassertEquals($userId, $result-\u003eid);
    }

    /**
     * 测试根据邮箱获取用户
     */
    public function testGetUserByEmail()
    {
        // 准备测试数据
        $email = 'test@example.com';
        $expectedUser = new User();
        $expectedUser-\u003eemail = $email;

        // 模拟User模型行为
        User::shouldReceive('query')-\u003eandReturnSelf();
        User::shouldReceive('where')-\u003ewith('email', $email)-\u003eandReturnSelf();
        User::shouldReceive('first')-\u003eonce()-\u003eandReturn($expectedUser);

        // 执行测试
        $result = $this-\u003eservice-\u003 egetUserByEmail($email);

        // 验证结果
        $this-\u003eassertInstanceOf(User::class, $result);
        $this-\u003eassertEquals($email, $result-\u003eemail);
    }

    /**
     * 测试验证用户凭证成功
     */
    public function testValidateCredentialsSuccess()
    {
        // 准备测试数据
        $email = 'test@example.com';
        $password = 'password123';
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $userMock = new User();
        $userMock-\u003eemail = $email;
        $userMock-\u003epassword_hash = $passwordHash;
        $userMock-\u003estatus = 1;

        // 模拟UserService行为
        $serviceMock = Mockery::mock(UserService::class . '[getUserByEmail]', [])-\u003emakePartial();
        $serviceMock-\u003eshouldReceive('getUserByEmail')-\u003ewith($email)-\u003eandReturn($userMock);

        // 执行测试
        $result = $serviceMock-\u003evalidateCredentials($email, $password);

        // 验证结果
        $this-\u003eassertInstanceOf(User::class, $result);
        $this-\u003eassertEquals($email, $result-\u003eemail);
    }

    /**
     * 测试验证用户凭证失败-密码错误
     */
    public function testValidateCredentialsWithWrongPassword()
    {
        // 准备测试数据
        $email = 'test@example.com';
        $correctPassword = 'password123';
        $wrongPassword = 'wrongpassword';
        $passwordHash = password_hash($correctPassword, PASSWORD_DEFAULT);
        
        $userMock = new User();
        $userMock-\u003eemail = $email;
        $userMock-\u003epassword_hash = $passwordHash;
        $userMock-\u003estatus = 1;

        // 模拟UserService行为
        $serviceMock = Mockery::mock(UserService::class . '[getUserByEmail]', [])-\u003emakePartial();
        $serviceMock-\u003eshouldReceive('getUserByEmail')-\u003ewith($email)-\u003eandReturn($userMock);

        // 执行测试
        $result = $serviceMock-\u003evalidateCredentials($email, $wrongPassword);

        // 验证结果
        $this-\u003eassertNull($result);
    }

    /**
     * 测试验证用户凭证失败-用户不存在
     */
    public function testValidateCredentialsWithNonExistingUser()
    {
        // 准备测试数据
        $email = 'nonexistent@example.com';
        $password = 'password123';

        // 模拟UserService行为
        $serviceMock = Mockery::mock(UserService::class . '[getUserByEmail]', [])-\u003emakePartial();
        $serviceMock-\u003eshouldReceive('getUserByEmail')-\u003ewith($email)-\u003eandReturn(null);

        // 执行测试
        $result = $serviceMock-\u003evalidateCredentials($email, $password);

        // 验证结果
        $this-\u003eassertNull($result);
    }

    /**
     * 测试修改用户密码成功
     */
    public function testChangePasswordSuccess()
    {
        // 准备测试数据
        $currentPassword = 'currentpassword';
        $newPassword = 'newpassword123';
        $passwordHash = password_hash($currentPassword, PASSWORD_DEFAULT);
        
        $userMock = Mockery::mock(User::class);
        $userMock-\u003epassword_hash = $passwordHash;
        $userMock-\u003eshouldReceive('save')-\u003eandReturn(true);

        // 执行测试
        $result = $this-\u003eservice-\u003echangePassword($userMock, $currentPassword, $newPassword);

        // 验证结果
        $this-\u003eassertTrue($result);
    }

    /**
     * 测试修改用户密码失败-当前密码错误
     */
    public function testChangePasswordWithWrongCurrentPassword()
    {
        // 准备测试数据
        $currentPassword = 'currentpassword';
        $wrongCurrentPassword = 'wrongpassword';
        $newPassword = 'newpassword123';
        $passwordHash = password_hash($currentPassword, PASSWORD_DEFAULT);
        
        $userMock = Mockery::mock(User::class);
        $userMock-\u003epassword_hash = $passwordHash;

        // 执行测试并验证异常
        $this-\u003eexpectException(\InvalidArgumentException::class);
        $this-\u003eexpectExceptionMessage('当前密码错误');
        $this-\u003eservice-\u003echangePassword($userMock, $wrongCurrentPassword, $newPassword);
    }

    /**
     * 测试修改用户密码失败-新密码太短
     */
    public function testChangePasswordWithShortNewPassword()
    {
        // 准备测试数据
        $currentPassword = 'currentpassword';
        $shortNewPassword = '123'; // 少于6位
        $passwordHash = password_hash($currentPassword, PASSWORD_DEFAULT);
        
        $userMock = Mockery::mock(User::class);
        $userMock-\u003epassword_hash = $passwordHash;

        // 执行测试并验证异常
        $this-\u003eexpectException(\InvalidArgumentException::class);
        $this-\u003eexpectExceptionMessage('新密码长度不能少于6位');
        $this-\u003eservice-\u003echangePassword($userMock, $currentPassword, $shortNewPassword);
    }

    /**
     * 测试更新用户信息成功
     */
    public function testUpdateUserSuccess()
    {
        // 准备测试数据
        $userMock = Mockery::mock(User::class);
        $userMock-\u003eid = 1;
        $userMock-\u003eusername = 'oldusername';
        $userMock-\u003eshouldReceive('save')-\u003eandReturn(true);
        
        $data = [
            'username' =\u003e 'newusername',
            'real_name' =\u003e 'New Name',
            'bio' =\u003e 'Updated bio'
        ];

        // 模拟User模型行为
        User::shouldReceive('query')-\u003eandReturnSelf();
        User::shouldReceive('where')-\u003eandReturnSelf();
        User::shouldReceive('where')-\u003ewith('username', $data['username'])-\u003eandReturnSelf();
        User::shouldReceive('where')-\u003ewith('id', '!=', $userMock-\u003eid)-\u003eandReturnSelf();
        User::shouldReceive('first')-\u003eonce()-\u003eandReturn(null);

        // 执行测试
        $result = $this-\u003eservice-\u003eupdateUser($userMock, $data);

        // 验证结果
        $this-\u003eassertInstanceOf(User::class, $result);
    }

    /**
     * 测试切换用户状态
     */
    public function testToggleUserStatus()
    {
        // 准备测试数据
        $userId = 1;
        $userMock = Mockery::mock(User::class);
        $userMock-\u003eid = $userId;
        $userMock-\u003estatus = 1;
        $userMock-\u003eshouldReceive('save')-\u003eandReturn(true);

        // 模拟UserService行为
        $serviceMock = Mockery::mock(UserService::class . '[getUserById]', [])-\u003emakePartial();
        $serviceMock-\u003eshouldReceive('getUserById')-\u003ewith($userId)-\u003eandReturn($userMock);

        // 执行测试
        $result = $serviceMock-\u003etoggleUserStatus($userId);

        // 验证结果
        $this-\u003eassertTrue($result);
    }

    /**
     * 测试切换用户状态失败-用户不存在
     */
    public function testToggleUserStatusWithNonExistingUser()
    {
        // 准备测试数据
        $userId = 999; // 不存在的用户ID

        // 模拟UserService行为
        $serviceMock = Mockery::mock(UserService::class . '[getUserById]', [])-\u003emakePartial();
        $serviceMock-\u003eshouldReceive('getUserById')-\u003ewith($userId)-\u003eandReturn(null);

        // 执行测试并验证异常
        $this-\u003eexpectException(\InvalidArgumentException::class);
        $this-\u003eexpectExceptionMessage('用户不存在');
        $serviceMock-\u003etoggleUserStatus($userId);
    }
}