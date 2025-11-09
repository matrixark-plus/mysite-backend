<?php

use Hyperf\DbConnection\Db;
use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class AddAuthFieldsToUsers extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 添加登录失败次数字段
            $table->integer('login_attempts')->default(0)->comment('登录失败次数');
            
            // 添加账号锁定状态字段
            $table->boolean('is_locked')->default(false)->comment('账号是否锁定');
            
            // 添加锁定过期时间字段
            $table->timestamp('lock_expire_time')->nullable()->comment('锁定过期时间');
        });
        
        // 添加索引以提高查询性能
        Db::statement("ALTER TABLE users ADD INDEX idx_is_locked_lock_expire_time (is_locked, lock_expire_time)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('login_attempts');
            $table->dropColumn('is_locked');
            $table->dropColumn('lock_expire_time');
        });
    }
}