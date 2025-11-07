<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\DB;

class CreateBlogTagRelationsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blog_tag_relations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('blog_id')->comment('博客ID');
            $table->unsignedBigInteger('tag_id')->comment('标签ID');
            $table->timestamps();
            
            // 外键关系
            $table->foreign('blog_id')->references('id')->on('blogs')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('blog_tags')->onDelete('cascade');
            
            // 复合唯一索引，确保一篇博客不能重复添加同一个标签
            $table->unique(['blog_id', 'tag_id']);
            
            // 索引设计
            $table->index('blog_id');
            $table->index('tag_id');
        });
        
        // 添加测试数据 - 将标签与博客关联起来
        DB::table('blog_tag_relations')->insert([
            ['blog_id' => 1, 'tag_id' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['blog_id' => 2, 'tag_id' => 3, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['blog_id' => 2, 'tag_id' => 4, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]
        ]);
        
        // 更新标签使用次数
        DB::table('blog_tags')->whereIn('id', [1, 3, 4])->increment('usage_count');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_tag_relations');
    }
}
