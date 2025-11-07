<?php

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\DB;

class CreateMindmapFavoritesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mindmap_favorites', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('mindmap_id')->comment('脑图ID');
            $table->string('notes', 500)->nullable()->comment('收藏笔记');
            $table->datetimes();
            
            // 外键关系
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            
            $table->foreign('mindmap_id')
                ->references('id')
                ->on('mindmaps')
                ->onDelete('cascade');
            
            // 索引设计
            $table->unique(['user_id', 'mindmap_id']); // 防止重复收藏
            $table->index('user_id');
            $table->index('mindmap_id');
            $table->index('created_at');
        });
        
        // 插入测试数据
        DB::table('mindmap_favorites')->insert([
            [
                'user_id' => 2,
                'mindmap_id' => 1,
                'notes' => '这个项目规划脑图很有用',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'user_id' => 1,
                'mindmap_id' => 2,
                'notes' => '收藏自己的学习笔记',
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
        Schema::dropIfExists('mindmap_favorites');
    }
}
