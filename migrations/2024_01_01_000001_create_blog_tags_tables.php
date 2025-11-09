<?php

use Hyperf\DbConnection\Db;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateBlogTagsTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 创建博客标签表
        Schema::create('blog_tags', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 50)->notNull()->comment('标签名称');
            $table->string('slug', 100)->notNull()->comment('标签别名');
            $table->text('description')->nullable()->comment('标签描述');
            $table->integer('use_count')->default(0)->comment('使用次数');
            $table->timestamps();
            
            // 添加唯一索引
            $table->unique('name', 'uk_tag_name');
            $table->unique('slug', 'uk_tag_slug');
        });
        
        // 创建博客标签关联表
        Schema::create('blog_tag_relations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('blog_id')->notNull()->comment('博客ID');
            $table->integer('tag_id')->notNull()->comment('标签ID');
            $table->timestamps();
            
            // 添加外键约束
            $table->foreign('blog_id')->references('id')->on('blogs')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('blog_tags')->onDelete('cascade');
            
            // 添加联合唯一索引，确保一个博客不会重复关联同一个标签
            $table->unique(['blog_id', 'tag_id'], 'uk_blog_tag');
            
            // 添加索引以提高查询性能
            $table->index('blog_id', 'idx_blog_id');
            $table->index('tag_id', 'idx_tag_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 先删除关联表，再删除标签表
        Schema::dropIfExists('blog_tag_relations');
        Schema::dropIfExists('blog_tags');
    }
}