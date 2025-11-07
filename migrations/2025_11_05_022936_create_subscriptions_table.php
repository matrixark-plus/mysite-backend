<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\DB;

class CreateSubscriptionsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID，游客订阅可为空');
            $table->string('email', 100)->notNullable()->comment('订阅邮箱');
            $table->string('type', 50)->default('blog_update')->comment('订阅类型(blog_update, newsletter等)');
            $table->string('status', 20)->default('pending')->comment('订阅状态(pending, active, cancelled)');
            $table->string('verification_token', 255)->nullable()->comment('验证令牌');
            $table->timestamp('verified_at')->nullable()->comment('验证时间');
            $table->timestamp('cancelled_at')->nullable()->comment('取消时间');
            $table->string('ip_address', 45)->nullable()->comment('订阅者IP地址');
            $table->json('meta')->nullable()->comment('元数据');
            $table->timestamps();
            $table->softDeletes();
            
            // 外键关系
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // 索引设计
            $table->index('user_id');
            $table->index('email');
            $table->index('type');
            $table->index('status');
            $table->unique(['email', 'type']); // 同一邮箱对同一类型只能订阅一次
        });
        
        // 添加测试数据
        DB::table('subscriptions')->insert([
            [
                'user_id' => 2,
                'email' => 'user2@example.com',
                'type' => 'blog_update',
                'status' => 'active',
                'verification_token' => 'verified_'.md5('user2@example.com'),
                'verified_at' => date('Y-m-d H:i:s'),
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'user_id' => null,
                'email' => 'guest@example.com',
                'type' => 'newsletter',
                'status' => 'pending',
                'verification_token' => md5('guest@example.com'.time()),
                'ip_address' => '192.168.1.100',
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
        Schema::dropIfExists('subscriptions');
    }
}
