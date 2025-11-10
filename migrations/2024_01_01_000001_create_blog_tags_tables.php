<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
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
            $table->string('name', 50)->notNullable();
            $table->string('slug', 100)->notNullable();
            $table->text('description')->nullable();
            $table->integer('use_count')->default(0);
            $table->timestamps();
        });

        // 创建博客标签关联表
        Schema::create('blog_tag_relations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('blog_id')->notNullable();
            $table->integer('tag_id')->notNullable();
            $table->timestamps();

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
