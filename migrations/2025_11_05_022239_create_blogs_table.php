<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\DB;

class CreateBlogsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blogs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->comment('作者ID');
            $table->unsignedBigInteger('category_id')->comment('分类ID');
            $table->string('title', 200)->comment('文章标题');
            $table->string('slug', 200)->unique()->comment('文章别名');
            $table->text('content')->comment('文章内容');
            $table->text('excerpt')->nullable()->comment('文章摘要');
            $table->string('cover_image', 255)->nullable()->comment('封面图片');
            $table->boolean('is_published')->default(false)->comment('是否发布');
            $table->integer('view_count')->default(0)->comment('浏览次数');
            $table->integer('comment_count')->default(0)->comment('评论数量');
            $table->integer('like_count')->default(0)->comment('点赞数量');
            $table->string('seo_title', 200)->nullable()->comment('SEO标题');
            $table->string('seo_keywords', 255)->nullable()->comment('SEO关键词');
            $table->text('seo_description')->nullable()->comment('SEO描述');
            $table->timestamps();
            $table->softDeletes();
            
            // 外键关系
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('blog_categories')->onDelete('cascade');
            
            // 索引设计
            $table->index('user_id');
            $table->index('category_id');
            $table->index('title');
            $table->index('slug');
            $table->index('is_published');
            $table->index('view_count');
            $table->fullText('title', 'content', 'excerpt');
        });
        
        // 添加测试数据
        DB::table('blogs')->insert([
            [
                'user_id' => 1,
                'category_id' => 1,
                'title' => 'Hyperf框架入门指南',
                'slug' => 'hyperf-getting-started',
                'content' => '<h2>Hyperf简介</h2><p>Hyperf是一个高性能的PHP协程框架...</p>',
                'excerpt' => '本文介绍Hyperf框架的基本概念和使用方法',
                'is_published' => true,
                'view_count' => 100,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'user_id' => 1,
                'category_id' => 3,
                'title' => 'Vue3 Composition API详解',
                'slug' => 'vue3-composition-api',
                'content' => '<h2>Composition API介绍</h2><p>Vue3引入的Composition API提供了更好的代码组织方式...</p>',
                'excerpt' => '深入探讨Vue3 Composition API的各种特性',
                'is_published' => true,
                'view_count' => 200,
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
        Schema::dropIfExists('blogs');
    }
}
