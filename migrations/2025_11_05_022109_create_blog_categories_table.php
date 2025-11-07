<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\DB;

class CreateBlogCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100)->unique()->comment('分类名称');
            $table->string('slug', 100)->unique()->comment('分类别名');
            $table->unsignedBigInteger('parent_id')->default(0)->comment('父分类ID，0表示顶级分类');
            $table->text('description')->nullable()->comment('分类描述');
            $table->integer('sort_order')->default(0)->comment('排序顺序');
            $table->timestamps();
            $table->softDeletes();
            
            // 索引设计
            $table->index('name');
            $table->index('slug');
            $table->index('parent_id');
            $table->index('sort_order');
        });
        
        // 添加测试数据
        DB::table('blog_categories')->insert([
            [
                'name' => '技术分享',
                'slug' => 'tech',
                'parent_id' => 0,
                'description' => '分享技术文章和教程',
                'sort_order' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'name' => '生活随笔',
                'slug' => 'life',
                'parent_id' => 0,
                'description' => '记录生活点滴',
                'sort_order' => 2,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'name' => '前端开发',
                'slug' => 'frontend',
                'parent_id' => 1,
                'description' => '前端技术相关文章',
                'sort_order' => 1,
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
        Schema::dropIfExists('blog_categories');
    }
}
