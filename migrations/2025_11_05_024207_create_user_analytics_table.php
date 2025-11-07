<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Query\Builder;

class CreateUserAnalyticsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_analytics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('user_id')->nullable()->comment('用户ID（可为空，访客）');
            $table->string('session_id', 255)->notNull()->comment('会话ID');
            $table->string('event_type', 50)->notNull()->comment('事件类型(page_view/click/scroll等)');
            $table->json('event_data')->nullable()->comment('事件详细数据');
            $table->string('url', 500)->notNull()->comment('访问URL');
            $table->string('referrer', 500)->nullable()->comment('来源URL');
            $table->string('user_agent', 255)->notNull()->comment('用户代理');
            $table->string('ip_address', 45)->notNull()->comment('IP地址');
            $table->string('browser', 50)->nullable()->comment('浏览器');
            $table->string('device', 50)->nullable()->comment('设备类型');
            $table->string('os', 50)->nullable()->comment('操作系统');
            $table->datetime('created_at')->notNull()->default('CURRENT_TIMESTAMP');
            
            // 外键约束
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            
            // 索引设计
            $table->index(['session_id', 'created_at'])->comment('支持会话分析查询');
            $table->index(['event_type', 'created_at'])->comment('支持事件类型统计');
            $table->index('user_id')->comment('支持用户相关查询');
        });
        
        // 插入测试数据
        $this->insertTestData();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_analytics');
    }
    
    /**
     * 插入测试数据
     */
    protected function insertTestData()
    {
        // 这里会在后续步骤中添加实际的测试数据
    }
}
