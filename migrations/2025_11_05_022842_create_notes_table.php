<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\DB;

class CreateNotesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('title', 255)->comment('笔记标题');
            $table->text('content')->comment('笔记内容');
            $table->string('type', 20)->default('text')->comment('笔记类型(text, markdown, code等)');
            $table->boolean('is_public')->default(false)->comment('是否公开');
            $table->boolean('is_pinned')->default(false)->comment('是否置顶');
            $table->integer('view_count')->default(0)->comment('浏览次数');
            $table->json('tags')->nullable()->comment('标签列表');
            $table->json('meta')->nullable()->comment('元数据');
            $table->timestamps();
            $table->softDeletes();
            
            // 外键关系
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // 索引设计
            $table->index('user_id');
            $table->index('is_public');
            $table->index('is_pinned');
            $table->index('created_at');
            $table->fullText('title', 'idx_notes_title_fulltext');
            $table->fullText('content', 'idx_notes_content_fulltext');
        });
        
        // 添加测试数据
        DB::table('notes')->insert([
            [
                'user_id' => 1,
                'title' => 'Hyperf框架学习笔记',
                'content' => '# Hyperf框架学习笔记\n\n## 1. 安装\n\n```bash\ncomposer create-project hyperf/hyperf-skeleton\n```\n\n## 2. 主要特性\n- 协程框架\n- 高性能\n- 灵活的组件系统',
                'type' => 'markdown',
                'is_public' => true,
                'is_pinned' => true,
                'tags' => json_encode(['PHP', 'Hyperf', '后端']),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'user_id' => 1,
                'title' => '项目开发计划',
                'content' => '1. 数据库设计\n2. API接口开发\n3. 前端页面开发\n4. 测试与部署',
                'type' => 'text',
                'is_public' => false,
                'is_pinned' => false,
                'tags' => json_encode(['计划', '项目管理']),
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
        Schema::dropIfExists('notes');
    }
}
