<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\DB;

class CreateMindmapsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mindmaps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->comment('创建者ID');
            $table->string('title', 255)->comment('脑图标题');
            $table->text('description')->nullable()->comment('脑图描述');
            $table->json('structure_data')->nullable()->comment('脑图结构数据');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft')->comment('状态');
            $table->boolean('is_public')->default(false)->comment('是否公开');
            $table->integer('view_count')->default(0)->comment('查看次数');
            $table->integer('favorite_count')->default(0)->comment('收藏次数');
            $table->datetimes();
            $table->softDeletes();
            
            // 外键关系
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            
            // 索引设计
            $table->index('user_id');
            $table->index('status');
            $table->index('is_public');
            $table->index('created_at');
            $table->fullText('title');
            $table->fullText('description');
        });
        
        // 插入测试数据
        DB::table('mindmaps')->insert([
            [
                'user_id' => 1,
                'title' => '项目规划脑图',
                'description' => '网站开发项目的整体规划和任务分配',
                'structure_data' => '{"root":{"title":"项目规划","children":[{"title":"需求分析"},{"title":"设计阶段"},{"title":"开发阶段"}]}}',
                'status' => 'published',
                'is_public' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'user_id' => 2,
                'title' => '学习笔记',
                'description' => '编程学习的知识点整理',
                'structure_data' => '{"root":{"title":"学习笔记","children":[{"title":"前端技术"},{"title":"后端技术"},{"title":"数据库"}]}}',
                'status' => 'draft',
                'is_public' => false,
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
        Schema::dropIfExists('mindmaps');
    }
}
