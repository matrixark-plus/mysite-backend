<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\AuthService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Context\ApplicationContext;
use Psr\Container\ContainerInterface;
use Qbhy\HyperfAuth\AuthManager;

/**
 * @Command
 */
class AuthTestCommand extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var AuthService
     */
    protected $authService;

    /**
     * @var AuthManager
     */
    protected $auth;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->authService = $container->get(AuthService::class);
        $this->auth = $container->get(AuthManager::class);

        parent::__construct('auth:test');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('测试认证模块功能');
    }

    public function handle()
    {
        $this->info('开始测试 hyperf-auth 认证模块...');
        
        try {
            // 测试 auth manager 是否正常工作
            $this->testAuthManager();
            
            // 测试 guard 是否配置正确
            $this->testAuthGuard();
            
            $this->info('✅ 认证模块测试完成，所有组件正常工作！');
        } catch (\Throwable $e) {
            $this->error('❌ 认证模块测试失败: ' . $e->getMessage());
            $this->error('错误详情: ' . $e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }

    /**
     * 测试 AuthManager 是否正常工作
     */
    protected function testAuthManager()
    {
        $this->info('测试 AuthManager...');
        
        if ($this->auth === null) {
            throw new \RuntimeException('AuthManager 未正确初始化');
        }
        
        $this->info('✅ AuthManager 初始化成功');
    }

    /**
     * 测试认证守卫配置
     */
    protected function testAuthGuard()
    {
        $this->info('测试 JWT Guard...');
        
        // 检查是否能获取到 jwt guard
        $guard = $this->auth->guard('jwt');
        
        if ($guard === null) {
            throw new \RuntimeException('无法获取 JWT Guard，请检查配置');
        }
        
        $this->info('✅ JWT Guard 获取成功');
        $this->info('配置详情:');
        
        // 输出配置信息
        $config = config('auth.guards.jwt');
        $this->info('  - 驱动: ' . $config['driver'] ?? '未配置');
        $this->info('  - 模型: ' . $config['provider']['model'] ?? '未配置');
        $this->info('  - TTL: ' . ($config['ttl'] ?? '未配置') . ' 秒');
    }
}