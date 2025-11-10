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

class CreateCommentLikesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 创建评论点赞表
        Schema::create('comment_likes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('comment_id')->notNull()->comment('评论ID');
            $table->integer('user_id')->notNull()->comment('点赞用户ID');
            $table->timestamps();

            // 添加外键约束
            $table->foreign('comment_id')->references('id')->on('comments')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // 添加联合唯一索引，确保一个用户对一个评论只能点赞一次
            $table->unique(['comment_id', 'user_id'], 'uk_comment_user');

            // 添加索引以提高查询性能
            $table->index('comment_id', 'idx_comment_id');
            $table->index('user_id', 'idx_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comment_likes');
    }
}
