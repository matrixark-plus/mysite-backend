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
use Hyperf\DbConnection\Db;

class UpdateUsersTableForRolePermission extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 检查users表是否存在
        if (Schema::hasTable('users')) {
            // 分步骤执行，避免事务问题

            // 第一步：添加新字段，先不删除旧字段
            if (Schema::hasColumn('users', 'is_active') && Schema::hasColumn('users', 'is_admin')) {
                // 添加新字段
                Schema::table('users', function (Blueprint $table) {
                    if (! Schema::hasColumn('users', 'status')) {
                        $table->string('status', 20)->default('active')->after('created_at');
                    }
                    if (! Schema::hasColumn('users', 'role')) {
                        $table->string('role', 20)->default('user')->after('status');
                    }
                });

                // 执行数据转换
                $this->convertUserData();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 回滚操作暂时不实现，因为需要先确保正向迁移成功
    }

    /**
     * 转换用户数据.
     */
    protected function convertUserData()
    {
        try {
            // 简单的数据转换，避免复杂的CASE语句
            Db::statement("UPDATE users SET status = 'active' WHERE is_active = true");
            Db::statement("UPDATE users SET status = 'inactive' WHERE is_active = false");
            Db::statement("UPDATE users SET role = 'admin' WHERE is_admin = true");
            Db::statement("UPDATE users SET role = 'user' WHERE is_admin = false");
        } catch (Exception $e) {
            // 记录错误但不抛出，让迁移继续
            echo '数据转换过程中出现错误：' . $e->message . "\n";
        }
    }
}
