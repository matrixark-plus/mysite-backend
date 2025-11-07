<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\DB;

class CreateSystemConfigsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key', 100)->notNullable()->unique()->comment('配置键名');
            $table->json('value')->comment('配置值，支持复杂数据结构');
            $table->string('type', 50)->default('string')->comment('配置类型(string, number, boolean, array, object)');
            $table->string('group', 50)->default('system')->comment('配置分组');
            $table->string('description', 255)->nullable()->comment('配置描述');
            $table->boolean('is_readonly')->default(false)->comment('是否只读');
            $table->boolean('is_hidden')->default(false)->comment('是否在管理界面隐藏');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建者ID');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('更新者ID');
            $table->timestamps();
            
            // 外键关系
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // 索引设计
            $table->index('key');
            $table->index('group');
            $table->index('created_at');
        });
        
        // 添加基础系统配置
        DB::table('system_configs')->insert([
            // 网站基本配置
            [
                'key' => 'site.name',
                'value' => json_encode('个人网站'),
                'type' => 'string',
                'group' => 'site',
                'description' => '网站名称',
                'is_readonly' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'key' => 'site.description',
                'value' => json_encode('这是一个个人作品集和博客网站'),
                'type' => 'string',
                'group' => 'site',
                'description' => '网站描述',
                'is_readonly' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'key' => 'site.logo',
                'value' => json_encode('/images/logo.png'),
                'type' => 'string',
                'group' => 'site',
                'description' => '网站Logo',
                'is_readonly' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            // 博客配置
            [
                'key' => 'blog.posts_per_page',
                'value' => json_encode(10),
                'type' => 'number',
                'group' => 'blog',
                'description' => '每页显示博客数量',
                'is_readonly' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'key' => 'blog.allow_comments',
                'value' => json_encode(true),
                'type' => 'boolean',
                'group' => 'blog',
                'description' => '是否允许评论',
                'is_readonly' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            // 邮箱配置
            [
                'key' => 'mail.enabled',
                'value' => json_encode(true),
                'type' => 'boolean',
                'group' => 'mail',
                'description' => '是否启用邮件功能',
                'is_readonly' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            // 用户配置
            [
                'key' => 'user.registration_enabled',
                'value' => json_encode(true),
                'type' => 'boolean',
                'group' => 'user',
                'description' => '是否允许用户注册',
                'is_readonly' => false,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            // SEO配置
            [
                'key' => 'seo.meta_keywords',
                'value' => json_encode(['个人网站', '作品集', '博客', 'Hyperf', 'PHP']),
                'type' => 'array',
                'group' => 'seo',
                'description' => '网站关键词',
                'is_readonly' => false,
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
        Schema::dropIfExists('system_configs');
    }
}
