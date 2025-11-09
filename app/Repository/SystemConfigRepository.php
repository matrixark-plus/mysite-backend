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

namespace App\Repository;

use Carbon\Carbon;
use Exception;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 系统配置数据访问层
 * 封装所有与系统配置相关的数据库操作.
 */
class SystemConfigRepository
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 根据key获取系统配置
     *
     * @param string $key 配置键
     * @return mixed 配置值
     */
    public function findByKey(string $key): mixed
    {
        try {
            $config = Db::table('system_configs')->where('key', $key)->first();
            if ($config) {
                $value = $config->value ?? null;
                // 尝试解析JSON值
                if (is_string($value) && (($value[0] === '{' && substr($value, -1) === '}') || ($value[0] === '[' && substr($value, -1) === ']'))) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $decoded;
                    }
                }
                return $value;
            }
            return null;
        } catch (Exception $e) {
            $this->logger->error('根据key获取系统配置失败: ' . $e->getMessage(), ['key' => $key]);
            return null;
        }
    }

    /**
     * 获取所有系统配置
     *
     * @return array<string, mixed> 配置数组，key为配置键，value为配置值
     */
    public function findAll(): array
    {
        try {
            $configs = Db::table('system_configs')->get()->toArray();
            $result = [];
            
            foreach ($configs as $config) {
                $key = $config->key ?? '';
                $value = $config->value ?? null;
                
                // 尝试解析JSON值
                if (is_string($value) && (($value[0] === '{' && substr($value, -1) === '}') || ($value[0] === '[' && substr($value, -1) === ']'))) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                    }
                }
                
                if ($key) {
                    $result[$key] = $value;
                }
            }
            
            return $result;
        } catch (Exception $e) {
            $this->logger->error('获取所有系统配置失败: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 更新或插入系统配置
     *
     * @param string $key 配置键
     * @param mixed $value 配置值
     * @return bool 是否成功
     */
    public function updateOrCreate(string $key, mixed $value): bool
    {
        try {
            return Db::transaction(function () use ($key, $value) {
                // 序列化配置值
                $valueStr = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                
                $now = Carbon::now()->toDateTimeString();
                
                // 检查是否存在
                $exists = Db::table('system_configs')->where('key', $key)->first();
                
                if ($exists) {
                    // 更新
                    $result = Db::table('system_configs')->where('key', $key)->update([
                        'value' => $valueStr,
                        'updated_at' => $now
                    ]);
                    return $result > 0;
                } else {
                    // 插入
                    $result = Db::table('system_configs')->insert([
                        'key' => $key,
                        'value' => $valueStr,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);
                    return $result;
                }
            });
        } catch (Exception $e) {
            $this->logger->error('更新或插入系统配置失败: ' . $e->getMessage(), ['key' => $key]);
            return false;
        }
    }

    /**
     * 批量更新系统配置
     *
     * @param array<string, mixed> $configs 配置数组，key为配置键，value为配置值
     * @return bool 是否成功
     */
    public function batchUpdate(array $configs): bool
    {
        try {
            return Db::transaction(function () use ($configs) {
                foreach ($configs as $key => $value) {
                    if (!$this->updateOrCreate($key, $value)) {
                        return false;
                    }
                }
                return true;
            });
        } catch (Exception $e) {
            $this->logger->error('批量更新系统配置失败: ' . $e->getMessage());
            return false;
        }
    }
}