<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\DB;

class CreateCommentsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->comment('评论用户ID');
            $table->string('commentable_type', 50)->comment('评论对象类型(blogs, works等)');
            $table->unsignedBigInteger('commentable_id')->comment('评论对象ID');
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父评论ID，0表示顶级评论');
            $table->text('content')->comment('评论内容');
            $table->integer('rating')->nullable()->comment('评分，1-5分');
            $table->integer('like_count')->default(0)->comment('点赞数量');
            $table->boolean('is_approved')->default(true)->comment('是否已审核');
            $table->string('ip_address', 45)->nullable()->comment('评论者IP地址');
            $table->json('user_agent')->nullable()->comment('用户代理信息');
            $table->timestamps();
            $table->softDeletes();
            
            // 外键关系
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // 自引用外键，用于回复功能
            $table->foreign('parent_id')->references('id')->on('comments')->onDelete('cascade');
            
            // 索引设计
            $table->index('user_id');
            $table->index(['commentable_type', 'commentable_id']);
            $table->index('parent_id');
            $table->index('is_approved');
            $table->index('created_at');
        });
        
        // 添加测试数据
        DB::table('comments')->insert([
            [
                'user_id' => 2,
                'commentable_type' => 'blogs',
                'commentable_id' => 1,
                'parent_id' => 0,
                'content' => '非常好的文章，学到了很多关于Hyperf框架的知识！',
                'is_approved' => true,
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'user_id' => 1,
                'commentable_type' => 'blogs',
                'commentable_id' => 1,
                'parent_id' => 1,
                'content' => '谢谢支持，后续会分享更多相关内容！',
                'is_approved' => true,
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'user_id' => 2,
                'commentable_type' => 'works',
                'commentable_id' => 1,
                'parent_id' => 0,
                'content' => '这个作品集网站设计得很精美！',
                'rating' => 5,
                'is_approved' => true,
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
        // 更新博客评论数量
        DB::table('blogs')->where('id', 1)->increment('comment_count', 2);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
}
