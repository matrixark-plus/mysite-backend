<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\DB;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('username', 50)->unique()->comment('用户名');
            $table->string('email', 100)->unique()->comment('邮箱');
            $table->string('password', 255)->comment('密码');
            $table->string('nickname', 50)->comment('昵称');
            $table->string('avatar', 255)->nullable()->comment('头像');
            $table->text('bio')->nullable()->comment('个人简介');
            $table->string('website', 255)->nullable()->comment('个人网站');
            $table->json('social_links')->nullable()->comment('社交链接');
            $table->boolean('is_active')->default(false)->comment('是否激活');
            $table->boolean('is_admin')->default(false)->comment('是否管理员');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->timestamps();
            $table->softDeletes();
            
            // 索引设计
            $table->index('username');
            $table->index('email');
            $table->index('is_active');
        });
        
        // 添加测试数据
        DB::table('users')->insert([
            [
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'nickname' => '管理员',
                'is_active' => true,
                'is_admin' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'username' => 'user1',
                'email' => 'user1@example.com',
                'password' => password_hash('user123', PASSWORD_DEFAULT),
                'nickname' => '普通用户',
                'is_active' => true,
                'is_admin' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
}
