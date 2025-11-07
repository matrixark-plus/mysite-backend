<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\DB;

class CreateWorksTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('works', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->comment('作者ID');
            $table->string('title', 200)->comment('作品标题');
            $table->text('description')->nullable()->comment('作品描述');
            $table->string('cover_image', 255)->nullable()->comment('封面图片');
            $table->json('work_files')->nullable()->comment('作品文件');
            $table->string('category', 50)->nullable()->comment('作品类别');
            $table->string('tags', 255)->nullable()->comment('作品标签');
            $table->boolean('is_published')->default(false)->comment('是否发布');
            $table->integer('view_count')->default(0)->comment('浏览次数');
            $table->integer('like_count')->default(0)->comment('点赞数量');
            $table->timestamps();
            $table->softDeletes();
            
            // 外键关系
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // 索引设计
            $table->index('user_id');
            $table->index('title');
            $table->index('category');
            $table->index('is_published');
            $table->index('view_count');
        });
        
        // 添加测试数据
        DB::table('works')->insert([
            [
                'user_id' => 1,
                'title' => '个人作品集网站',
                'description' => '使用Vue.js和Laravel开发的个人作品集展示网站',
                'cover_image' => 'https://example.com/covers/portfolio.jpg',
                'work_files' => json_encode(['main_file' => 'portfolio.zip', 'demo_url' => 'https://demo.com/portfolio']),
                'category' => '网站开发',
                'tags' => 'Vue.js,Laravel,响应式设计',
                'is_published' => true,
                'view_count' => 150,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'user_id' => 2,
                'title' => '电商管理系统',
                'description' => '基于Hyperf框架开发的电商后台管理系统',
                'cover_image' => 'https://example.com/covers/ecommerce.jpg',
                'work_files' => json_encode(['main_file' => 'ecommerce-system.zip']),
                'category' => '系统开发',
                'tags' => 'Hyperf,MySQL,Redis',
                'is_published' => true,
                'view_count' => 88,
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
        Schema::dropIfExists('works');
    }
}
