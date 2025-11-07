<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Query\Builder;

class CreateNoteVersionsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('note_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('note_id')->notNull()->comment('笔记ID，关联notes表');
            $table->longText('content_snapshot')->notNull()->comment('内容快照');
            $table->string('title_snapshot', 255)->notNull()->comment('标题快照');
            $table->integer('version_number')->notNull()->comment('版本号');
            $table->integer('created_by')->notNull()->comment('创建人ID，关联users表');
            $table->datetime('created_at')->notNull()->default('CURRENT_TIMESTAMP');
            
            // 外键约束
            $table->foreign('note_id')->references('id')->on('notes')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            
            // 索引设计
            $table->index(['note_id', 'version_number'])->comment('支持笔记版本查询和排序');
            $table->index('created_by')->comment('支持创建人查询');
        });
        
        // 插入测试数据
        $this->insertTestData();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('note_versions');
    }
    
    /**
     * 插入测试数据
     */
    protected function insertTestData()
    {
        // 这里会在后续步骤中添加实际的测试数据
    }
}
