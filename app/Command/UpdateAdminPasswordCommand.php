<?php

declare(strict_types=1);

namespace App\Command;

use App\Model\User;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;

/**
 * @Command
 */
class UpdateAdminPasswordCommand extends HyperfCommand
{
    /**
     * 执行的命令行
     *
     * @var string
     */
    protected ?string $name = 'user:update-admin-password';
    
    /**
     * 密码参数
     *
     * @var string
     */
    protected string $password = 'admin123';
    
    public function handle()
    {
        $this->output->writeln('开始更新admin用户密码...');
        
        // 使用事务确保操作安全
        Db::transaction(function () {
            // 获取admin用户
            $user = User::query()->where('username', 'admin')->first();
            
            if (!$user) {
                $this->output->error('错误: 未找到admin用户');
                return;
            }
            
            // 使用setPasswordAttribute方法设置密码
            $user->password = $this->password; // 自动通过setPasswordAttribute生成密码哈希
            
            // 保存更新
            $user->save();
            
            $this->output->success('admin用户密码更新成功！密码已设置为：' . $this->password);
            $this->output->writeln('密码哈希值：' . $user->password_hash);
        });
        
        $this->output->writeln('操作完成！');
    }
    
    /**
     * 配置命令
     */
    protected function configure()
    {
        parent::configure();
        $this->setDescription('更新admin用户密码，使用User模型的setPasswordAttribute确保密码哈希格式正确');
    }
}