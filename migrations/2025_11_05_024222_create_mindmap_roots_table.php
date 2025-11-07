<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Query\Builder;

class CreateMindmapRootsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mindmap_roots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title', 255)->notNull()->comment('根节点标题');
            $table->text('description')->nullable()->comment('描述');
            $table->string('screenshot_path', 255)->nullable()->comment('脑图截图路径');
            $table->integer('creator_id')->notNull()->comment('创建者ID，关联users表');
            $table->tinyInteger('is_public')->notNull()->default(1)->comment('是否公开');
            $table->datetime('created_at')->notNull()->default('CURRENT_TIMESTAMP');
            $table->datetime('updated_at')->notNull()->default('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
            
            // 外键约束
            $table->foreign('creator_id')->references('id')->on('users')->onDelete('cascade');
            
            // 索引设计
            $table->index(['is_public', 'created_at'])->comment('支持公开状态排序查询');
            $table->index(['creator_id', 'created_at'])->comment('支持用户的脑图列表');
            $table->index('title')->comment('支持标题搜索');
        });
        
        // 插入测试数据
        $this->insertTestData();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mindmap_roots');
    }
    
    /**
     * 插入测试数据
     */
    protected function insertTestData()
    {
        // 这里会在后续步骤中添加实际的测试数据
    }
}
