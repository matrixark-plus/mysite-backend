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

class FinalizeUsersTableRolePermission extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 检查users表是否存在
        if (Schema::hasTable('users')) {
            // 在闭包外部检查所有字段和索引的存在性
            $hasIsActive = Schema::hasColumn('users', 'is_active');
            $hasIsAdmin = Schema::hasColumn('users', 'is_admin');
            $hasStatus = Schema::hasColumn('users', 'status');
            $hasRole = Schema::hasColumn('users', 'role');

            // 1. 只在新字段存在且旧字段也存在的情况下执行删除
            if ($hasStatus && $hasRole && ($hasIsActive || $hasIsAdmin)) {
                Schema::table('users', function (Blueprint $table) use ($hasIsActive, $hasIsAdmin) {
                    // 删除旧字段
                    if ($hasIsActive) {
                        $table->dropColumn('is_active');
                    }
                    if ($hasIsAdmin) {
                        $table->dropColumn('is_admin');
                    }
                });
            }

            // 2. 单独执行索引添加操作
            try {
                // 先检查email唯一索引是否已存在（使用原生SQL）
                $emailIndexExists = Db::selectOne("SHOW INDEX FROM users WHERE Column_name = 'email' AND Non_unique = 0");
                if (! $emailIndexExists) {
                    // 使用原生SQL添加email唯一索引
                    Db::statement('ALTER TABLE users ADD UNIQUE INDEX users_email_unique (email)');
                }

                // 检查(status, role)复合索引是否已存在
                $statusRoleIndexExists = Db::selectOne("SHOW INDEX FROM users WHERE Column_name = 'status' AND Key_name != 'PRIMARY'");
                if (! $statusRoleIndexExists) {
                    // 使用原生SQL添加(status, role)复合索引
                    Db::statement('ALTER TABLE users ADD INDEX idx_status_role (status, role)');
                }
            } catch (Exception $e) {
                // 捕获并忽略索引操作中的错误
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 检查users表是否存在
        if (Schema::hasTable('users')) {
            // 1. 删除新索引（使用原生SQL）
            try {
                // 删除(status, role)复合索引
                Db::statement('DROP INDEX idx_status_role ON users');
                // 删除email唯一索引
                Db::statement('DROP INDEX users_email_unique ON users');
            } catch (Exception $e) {
                // 忽略错误
            }

            // 2. 添加回旧字段（如果不存在）
            $hasIsActive = Schema::hasColumn('users', 'is_active');
            $hasIsAdmin = Schema::hasColumn('users', 'is_admin');

            if (! $hasIsActive || ! $hasIsAdmin) {
                Schema::table('users', function (Blueprint $table) use ($hasIsActive, $hasIsAdmin) {
                    if (! $hasIsActive) {
                        $table->boolean('is_active')->default(true);
                    }
                    if (! $hasIsAdmin) {
                        $table->boolean('is_admin')->default(false);
                    }
                });
            }

            // 3. 恢复旧索引
            try {
                // 检查旧索引是否存在
                $oldIndexExists = Db::selectOne("SHOW INDEX FROM users WHERE Key_name = 'idx_is_active_is_admin'");
                if (! $oldIndexExists) {
                    Db::statement('ALTER TABLE users ADD INDEX idx_is_active_is_admin (is_active, is_admin)');
                }
            } catch (Exception $e) {
                // 忽略错误
            }
        }
    }
}
