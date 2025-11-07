<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Query\Builder;

class CreateNodeLinksTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('node_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('source_node_id')->notNull()->comment('源节点ID，关联mindmap_nodes表');
            $table->integer('target_node_id')->notNull()->comment('目标节点ID，关联mindmap_nodes表');
            $table->string('link_type', 50)->notNull()->default('bidirectional')->comment('链接类型(bidirectional/unidirectional)');
            $table->string('label', 100)->nullable()->comment('链接标签');
            $table->datetime('created_at')->notNull()->default('CURRENT_TIMESTAMP');
            
            // 外键约束
            $table->foreign('source_node_id')->references('id')->on('mindmap_nodes')->onDelete('cascade');
            $table->foreign('target_node_id')->references('id')->on('mindmap_nodes')->onDelete('cascade');
            
            // 索引设计
            $table->index('source_node_id')->comment('支持源节点查询');
            $table->index('target_node_id')->comment('支持目标节点查询');
            $table->index('link_type')->comment('支持链接类型查询');
            $table->index(['source_node_id', 'target_node_id'])->comment('支持节点对查询');
            $table->index(['target_node_id', 'source_node_id'])->comment('支持反向节点对查询');
        });
        
        // 插入测试数据
        $this->insertTestData();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('node_links');
    }
    
    /**
     * 插入测试数据
     */
    protected function insertTestData()
    {
        // 这里会在后续步骤中添加实际的测试数据
    }
}
