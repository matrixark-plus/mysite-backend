<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Query\Builder;

class CreateMindmapNodesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mindmap_nodes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('root_id')->notNull()->comment('根节点ID，关联mindmap_roots表');
            $table->integer('parent_id')->nullable()->comment('父节点ID，自关联');
            $table->string('title', 255)->notNull()->comment('节点标题');
            $table->enum('node_type', ['node', 'note_link'])->notNull()->comment('节点类型(node/note_link)');
            $table->integer('note_id')->nullable()->comment('关联笔记ID（仅node_type=note_link时有效）');
            $table->float('position_x')->nullable()->comment('节点X坐标');
            $table->float('position_y')->nullable()->comment('节点Y坐标');
            $table->datetime('created_at')->notNull()->default('CURRENT_TIMESTAMP');
            $table->datetime('updated_at')->notNull()->default('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
            
            // 外键约束
            $table->foreign('root_id')->references('id')->on('mindmap_roots')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('mindmap_nodes')->onDelete('cascade');
            $table->foreign('note_id')->references('id')->on('notes')->onDelete('set null');
            
            // 索引设计
            $table->index(['root_id', 'parent_id'])->comment('支持获取脑图的节点树结构');
            $table->index(['node_type', 'note_id'])->comment('支持按类型和关联笔记查询');
        });
        
        // 插入测试数据
        $this->insertTestData();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mindmap_nodes');
    }
    
    /**
     * 插入测试数据
     */
    protected function insertTestData()
    {
        // 这里会在后续步骤中添加实际的测试数据
    }
}
