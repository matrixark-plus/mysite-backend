<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\DB;

class CreateContactFormsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contact_forms', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID，游客提交可为空');
            $table->string('name', 100)->notNullable()->comment('提交者姓名');
            $table->string('email', 100)->notNullable()->comment('提交者邮箱');
            $table->string('subject', 255)->notNullable()->comment('主题');
            $table->text('message')->notNullable()->comment('留言内容');
            $table->string('status', 20)->default('pending')->comment('处理状态(pending, processing, resolved, closed)');
            $table->text('admin_reply')->nullable()->comment('管理员回复');
            $table->unsignedBigInteger('replied_by')->nullable()->comment('回复管理员ID');
            $table->timestamp('replied_at')->nullable()->comment('回复时间');
            $table->string('phone', 20)->nullable()->comment('电话号码');
            $table->string('ip_address', 45)->nullable()->comment('提交者IP地址');
            $table->json('user_agent')->nullable()->comment('用户代理信息');
            $table->json('meta')->nullable()->comment('元数据');
            $table->timestamps();
            $table->softDeletes();
            
            // 外键关系
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('replied_by')->references('id')->on('users')->onDelete('set null');
            
            // 索引设计
            $table->index('user_id');
            $table->index('email');
            $table->index('status');
            $table->index('created_at');
            $table->fullText('subject', 'idx_contact_subject_fulltext');
            $table->fullText('message', 'idx_contact_message_fulltext');
        });
        
        // 添加测试数据
        DB::table('contact_forms')->insert([
            [
                'user_id' => 2,
                'name' => '测试用户',
                'email' => 'user2@example.com',
                'subject' => '关于网站功能的咨询',
                'message' => '您好，我想了解一下网站的会员功能，请问有详细的介绍文档吗？',
                'status' => 'processing',
                'ip_address' => '127.0.0.1',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'user_id' => null,
                'name' => '访客',
                'email' => 'visitor@example.com',
                'subject' => '网站反馈',
                'message' => '网站整体设计很美观，但在移动端访问时有些按钮点击不灵敏。',
                'status' => 'pending',
                'phone' => '13800138000',
                'ip_address' => '192.168.1.101',
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
        Schema::dropIfExists('contact_forms');
    }
}
