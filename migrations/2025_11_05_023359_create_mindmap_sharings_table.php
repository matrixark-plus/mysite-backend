<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\DB;

class CreateMindmapSharingsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mindmap_sharings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('mindmap_id')->comment('脑图ID');
            $table->unsignedBigInteger('from_user_id')->comment('分享者ID');
            $table->unsignedBigInteger('to_user_id')->nullable()->comment('接收者ID');
            $table->string('share_code', 64)->unique()->comment('分享码');
            $table->enum('permission', ['view', 'edit', 'comment'])->default('view')->comment('权限类型');
            $table->enum('share_type', ['direct', 'link'])->default('link')->comment('分享类型');
            $table->timestamp('expires_at')->nullable()->comment('过期时间');
            $table->boolean('is_active')->default(true)->comment('是否有效');
            $table->datetimes();
            
            // 外键关系
            $table->foreign('mindmap_id')
                ->references('id')
                ->on('mindmaps')
                ->onDelete('cascade');
            
            $table->foreign('from_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            
            $table->foreign('to_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            
            // 索引设计
            $table->index('mindmap_id');
            $table->index('from_user_id');
            $table->index('to_user_id');
            $table->index('share_code');
            $table->index('expires_at');
            $table->index('is_active');
        });
        
        // 插入测试数据
        DB::table('mindmap_sharings')->insert([
            [
                'mindmap_id' => 1,
                'from_user_id' => 1,
                'to_user_id' => null,
                'share_code' => 'share123456',
                'permission' => 'view',
                'share_type' => 'link',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'mindmap_id' => 1,
                'from_user_id' => 1,
                'to_user_id' => 2,
                'share_code' => 'usershare789',
                'permission' => 'edit',
                'share_type' => 'direct',
                'expires_at' => null,
                'is_active' => true,
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
        Schema::dropIfExists('mindmap_sharings');
    }
}
